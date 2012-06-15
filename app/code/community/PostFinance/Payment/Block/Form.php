<?php

class PostFinance_Payment_Block_Form extends Mage_Payment_Block_Form_Cc
{
    /**
     * Init postfinance payment form
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('postfinance/form.phtml');
    }

    /**
     * get postfinance config
     *
     * @return PostFinance_Payment_Model_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('postfinance/config');
    }

    public function getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    public function getCcBrands()
    {
        return explode(',', $this->getConfig()->getAcceptedCcTypes());
    }

    public function getDirectDebitCountryIds()
    {
        return explode(',', $this->getConfig()->getDirectDebitCountryIds());
    }

    public function getBankTransferCountryIds()
    {
        return explode(',', $this->getConfig()->getBankTransferCountryIds());
    }
}
