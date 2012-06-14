<?php

class PostFinance_Payment_Block_Placeform extends Mage_Core_Block_Template
{
    public function __construct()
    {
    }

    public function getConfig()
    {
        return Mage::getModel('postfinance/config');
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * postfinance payment API instance
     *
     * @return PostFinance_Payment_Model_Payment_Abstract
     */
    protected function _getApi()
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($this->getCheckout()->getLastRealOrderId());
        return $order->getPayment()->getMethodInstance();
    }

    /**
     * Return order instance with loaded information by increment id
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _getOrder()
    {
        if ($this->getOrder()) {
            $order = $this->getOrder();
        } else if ($this->getCheckout()->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($this->getCheckout()->getLastRealOrderId());
        } else {
            return null;
        }
        return $order;
    }

    /**
     * Get Form data by using postfinance payment api
     *
     * @return array
     */
    public function getFormData()
    {
        return $this->_getApi()->getFormFields($this->_getOrder(), $this->getRequest()->getParams());
    }

    /**
     * Getting gateway url
     *
     * @return string
     */
    public function getFormAction()
    {
        return $this->getRequest()->isPost() || is_null($this->getQuestion()) ? $this->getConfig()->getFrontendGatewayPath() : Mage::getUrl('*/*/*');
    }

    public function getQuestion()
    {
        return $this->_getApi()->getQuestion($this->_getOrder(), $this->getRequest()->getParams());
    }

    public function getQuestionedFormFields()
    {
        return $this->_getApi()->getQuestionedFormFields($this->_getOrder(), $this->getRequest()->getParams());
    }
}
