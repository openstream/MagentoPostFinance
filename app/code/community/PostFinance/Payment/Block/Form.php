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

    public function getPSPID()
    {
        return Mage::getModel('postfinance/config')->getPSPID();
    }

    public function getAcceptUrl()
    {
        return Mage::getModel('postfinance/config')->getAcceptUrl();
    }

    public function getExceptionUrl()
    {
        return Mage::getModel('postfinance/config')->getExceptionUrl();
    }

    public function getAliasGatewayUrl()
    {
        return Mage::getModel('postfinance/config')->getAliasGatewayUrl();
    }

    public function getSaveCcBrandUrl()
    {
        return Mage::getModel('postfinance/config')->getSaveCcBrandUrl();
    }

    public function getGenerateHashUrl()
    {
        return Mage::getModel('postfinance/config')->getGenerateHashUrl();
    }

    public function getCcSaveAliasUrl()
    {
        return Mage::getModel('postfinance/config')->getCcSaveAliasUrl();
    }

    public function getRegisterDirectDebitPaymentUrl()
    {
        return Mage::getModel('postfinance/config')->getRegisterDirectDebitPaymentUrl();
    }
}
