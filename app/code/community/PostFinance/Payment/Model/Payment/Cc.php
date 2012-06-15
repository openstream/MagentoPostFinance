<?php

class PostFinance_Payment_Model_Payment_Cc extends PostFinance_Payment_Model_Payment_Abstract
{
    /** Check if can capture directly from the backend */
    protected $_canBackendDirectCapture = true;

    /** info source path */
    protected $_infoBlockType = 'postfinance/info_cc';

    /** payment code */
    protected $_code = 'postfinance_cc';

    /** payment code */
    public function getPaymentCode($payment = null) {
        if ('PostFinance + card' == $this->getCardBrand($payment)) {
            return 'PostFinance Card';
        }
        if ('UNEUROCOM' == $this->getCardBrand($payment)) {
            return 'UNEUROCOM';
        }
        return 'CreditCard';
    }

    /**
     * @param $payment Mage_Payment_Model_Info
     * @return mixed
     */
    public function getCardBrand($payment = null) {
        if (is_null($payment)) {
            $payment = Mage::getSingleton('checkout/session')->getQuote()->getPayment();
        }
        return $payment->getAdditionalInformation('CC_BRAND');
    }

    /**
     * @param $payment Mage_Payment_Model_Info
     * @return mixed
     */
    public function getOrderPlaceRedirectUrl($payment = null)
    {
        if ($this->hasBrandAliasInterfaceSupport($payment)) {
            if ('' == $this->getHtmlAnswer($payment)){
                return false; // Prevent redirect on cc payment
            } else {
                return $this->getConfig()->getPostFinanceUrl('placeform3dsecure', true);
            }
        }
        return parent::getOrderPlaceRedirectUrl();
    }

    /**
     * @param $payment Mage_Payment_Model_Info
     * @return mixed
     */
    public function getHtmlAnswer($payment = null) {
        if (is_null($payment)) {
            /* @var $order Mage_Sales_Model_Order */
            $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', Mage::getSingleton('checkout/session')->getQuote()->getId());
            $payment = $order->getPayment();
        }
        return $payment->getAdditionalInformation('HTML_ANSWER');
    }

    /**
     * only some brands are supported to be integrated into onepage checkout
     * 
     * @return array
     */
    public function getBrandsForAliasInterface()
    {
        return array(
            'American Express',
            'Billy',
            'Diners Club',
            'MaestroUK',
            'MasterCard',
            'VISA',
        );
    }

    /**
     * if cc brand supports PostFinance alias interface
     * 
     * @param Mage_Payment_Model_Info $payment 
     *
     * @return bool
     */
    public function hasBrandAliasInterfaceSupport($payment = null)
    {
        return in_array(
            $this->getCardBrand($payment),
            $this->getBrandsForAliasInterface()
        );
    }
}
