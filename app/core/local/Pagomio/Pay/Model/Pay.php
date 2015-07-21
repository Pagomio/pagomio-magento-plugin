<?php

class Pagomio_Pay_Model_Pay extends Mage_Payment_Model_Method_Abstract
{
    /**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    */
    protected $_code = 'pagomio';
    protected $_canUseForMultishipping  = false;
	
	protected $_formBlockType = 'pagomio/form';
 	protected $_infoBlockType = 'pagomio/info';
	
    /**
     * Return Order place direct url
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('pagomio/payment/redirect', array('_secure' => true));
    }

    public function getUrlRedirect(){

        $checkout = Mage::getSingleton('checkout/session');
        $orderIncrementId = $checkout->getLastRealOrderId();
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

        $client_id = Mage::getStoreConfig( 'payment/pagomio/client_id' );
        $secret_id = Mage::getStoreConfig( 'payment/pagomio/secret_id' );
        $transaction_mode = Mage::getStoreConfig( 'payment/pagomio/transaction_mode' );

        require_once  __DIR__  .'/../SDK/pagomio-sdk-php/pagomio.php';
        require_once  __DIR__ . '/../SDK/Requests/Requests.php';
        Requests::register_autoloader();

        $pagomio = new Pagomio\Pagomio($client_id,$secret_id,($transaction_mode=='sandbox'));

        $BAddress = $order->getBillingAddress();
        $description = '';
        $items = $order->getAllItems();
        if ($items)
        {
            foreach($items as $item)
            {
                if ($item->getParentItem()) continue;
                $description .= $item->getName() . '; ';
            }
        }
        $description = rtrim($description, '; ');
        $description = (strlen($description)>150) ? substr($description, 0, 140) . '...' : $description;

        $userData = new Pagomio\UserData();
        $userData->names = $BAddress->getFirstname();
        $userData->lastNames = $BAddress->getLastname();
        $userData->identificationType = 'CC'; # Allow: CC, TI, PT, NIT
        $userData->identification = $BAddress->getId();
        $userData->email = $BAddress->getEmail();

        $paymentData = new Pagomio\PaymentData();
        $paymentData->currency = $order->getBaseCurrencyCode();
        $paymentData->reference = $orderIncrementId;
        $paymentData->totalAmount = number_format($order->getGrandTotal(),2,'.','');
        $paymentData->taxAmount = number_format($order->getTaxAmount(),2,'.','');
        $taxReturnBase = number_format(($paymentData->totalAmount - $paymentData->taxAmount),2,'.','');;
        if($paymentData->taxAmount == 0) $taxReturnBase = '0.00';
        $paymentData->devolutionBaseAmount = $taxReturnBase;
        $paymentData->description = $description;

        $enterpriseData = new Pagomio\EnterpriseData();
        $enterpriseData->url_redirect = Mage::getUrl('pagomio/payment/success', array('_secure' => true));

        $aut = new Pagomio\AuthorizePayment();
        $aut->enterpriseData = $enterpriseData;
        $aut->paymentData = $paymentData;
        $aut->userData = $userData;

        $response = $pagomio->getToken($aut);
        if($response->success) {
            return $response->url;
        }
        throw new Exception($response->errorMessage);
    }

    public function paymentResponse(){
        $client_id = Mage::getStoreConfig( 'payment/pagomio/client_id' );
        $secret_id = Mage::getStoreConfig( 'payment/pagomio/secret_id' );
        $transaction_mode = Mage::getStoreConfig( 'payment/pagomio/transaction_mode' );

        require_once  __DIR__  .'/../SDK/pagomio-sdk-php/pagomio.php';
        require_once  __DIR__ . '/../SDK/Requests/Requests.php';
        Requests::register_autoloader();

        $pagomio = new Pagomio\Pagomio($client_id,$secret_id,($transaction_mode=='sandbox'));
        $response = $pagomio->getRequestPayment();
        if(!$response){
           return false;
        }
        $order = Mage::getModel('sales/order')->loadByIncrementId($response->reference);

        $strResponse = '';
        foreach($response as $key=>$value){
            $strResponse .= "<b>$key:</b> $value <br/>";
        }

        $status = $order->getStatus();
        if(in_array($response->status, array(\Pagomio\Pagomio::TRANSACTION_SUCCESS ,\Pagomio\Pagomio::TRANSACTION_PENDING ))){
            $payment = $order->getPayment();
            $payment->setTransactionId($response->transaction_id);
            if($response->status == \Pagomio\Pagomio::TRANSACTION_SUCCESS && $status != 'complete'){
                $payment->registerCaptureNotification( $response->total_amount );
                $order->addStatusToHistory('complete', $strResponse);
                $payment->save();
            }elseif($response->status == \Pagomio\Pagomio::TRANSACTION_PENDING && $status != 'pending'){
                $order->addStatusToHistory('pending', $strResponse);
            }
            $order->save();
            return true;
        }else{
            $quote = Mage::getModel('sales/quote')->load( $order->getQuoteId() );
            $quote->setIsActive( true )->save();
            $order->cancel()->save();
            return false;
        }
    }

    /**
     * Return true if the method can be used at this time
     *
     * @return bool
     */
    public function isAvailable($quote=null)
    {
        return (Mage::getStoreConfig( 'payment/pagomio/active'));
    }
}
