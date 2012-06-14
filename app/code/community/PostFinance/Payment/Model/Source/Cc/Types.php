<?php

class PostFinance_Payment_Model_Source_Cc_Types
{
    protected $types = array(
        'AIRPLUS',
        'American Express',
        'Aurora',
        'Aurore',
        'Billy',
        'BCMC',
        'CB',
        'Cofinoga',
        'Dankort',
        'Diners Club',
        'JCB',
        'Maestro',
        'MaestroUK',
        'MasterCard',
        'NetReserve',
        'PRIVILEGE',
        'PostFinance + card',
        'Solo',
        'UATP',
        'UNEUROCOM',
        'VISA',
    );
    
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        foreach ($this->types as $type) {
            $options[] = array(
                'value' => $type,
                'label' => Mage::helper('postfinance')->__($type)
            );
        }
        return $options;
    }
}
