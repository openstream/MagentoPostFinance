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
        return $this->getConfig()->getPSPID();
    }

    public function getAcceptUrl()
    {
        return $this->getConfig()->getAcceptUrl();
    }

    public function getExceptionUrl()
    {
        return $this->getConfig()->getExceptionUrl();
    }

    public function getAliasGatewayUrl()
    {
        return $this->getConfig()->getAliasGatewayUrl();
    }

    public function getSaveCcBrandUrl()
    {
        return $this->getConfig()->getSaveCcBrandUrl();
    }

    public function getGenerateHashUrl()
    {
        return $this->getConfig()->getGenerateHashUrl();
    }

    public function getCcSaveAliasUrl()
    {
        return $this->getConfig()->getCcSaveAliasUrl();
    }

    public function getRegisterDirectDebitPaymentUrl()
    {
        return $this->getConfig()->getRegisterDirectDebitPaymentUrl();
    }
}
