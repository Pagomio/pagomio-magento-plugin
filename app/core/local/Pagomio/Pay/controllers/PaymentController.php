<?php
class Pagomio_Pay_PaymentController extends Mage_Core_Controller_Front_Action {

	public function redirectAction()
	{
        $pagomio = Mage::getModel('pagomio/pay');
        $url = $pagomio->getUrlRedirect();
        return $this->_redirectUrl($url);
	}
	
	public function successAction()
	{
        $pagomio = Mage::getModel('pagomio/pay');
        if($pagomio->paymentResponse()){
            return $this->_redirect('checkout/onepage/success');
        }else{
            return $this->_redirect('checkout/onepage/failure');
        }
	}
}