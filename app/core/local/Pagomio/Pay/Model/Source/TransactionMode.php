<?php

class Pagomio_Pay_Model_Source_TransactionMode
{
    public function toOptionArray()
    {
        $options =  array();
        $options[] = array(
            	   'value' => 'sandbox',
            	   'label' => 'Sandbox'
        );
		$options[] = array(
            	   'value' => 'live',
            	   'label' => 'Live'
        );

        return $options;
    }
}