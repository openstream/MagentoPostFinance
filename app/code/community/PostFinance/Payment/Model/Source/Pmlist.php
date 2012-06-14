<?php

class PostFinance_Payment_Model_Source_Pmlist
{
    /**
     * Prepare postfinance payment block layout as option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => PostFinance_Payment_Model_Payment_Abstract::PMLIST_HORIZONTAL_LEFT, 'label' => Mage::helper('postfinance')->__('Horizontally grouped logo with group name on left')),
            array('value' => PostFinance_Payment_Model_Payment_Abstract::PMLIST_HORIZONTAL, 'label' => Mage::helper('postfinance')->__('Horizontally grouped logo with no group name')),
            array('value' => PostFinance_Payment_Model_Payment_Abstract::PMLIST_VERTICAL, 'label' => Mage::helper('postfinance')->__('Verical list')),
        );
    }
}
