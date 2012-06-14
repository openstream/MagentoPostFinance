<?php

class PostFinance_Payment_Model_Source_Template
{
    /**
     * Prepare postfinance template mode list as option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => PostFinance_Payment_Model_Payment_Abstract::TEMPLATE_POSTFINANCE, 'label' => Mage::helper('postfinance')->__('PostFinance')),
            array('value' => PostFinance_Payment_Model_Payment_Abstract::TEMPLATE_MAGENTO, 'label' => Mage::helper('postfinance')->__('Magento')),
        );
    }
}
