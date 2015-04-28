<?php

class Pagomio_Pay_Block_Form extends Mage_Payment_Block_Form
{
	protected function _construct()
    {
        parent::_construct();
		
		$mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplate('pagomio/mark.phtml');
		
        $this->setTemplate('pagomio/form.phtml')->setMethodTitle('') // Output payu mark, omit title
            ->setMethodLabelAfterHtml($mark->toHtml());
    }
}
