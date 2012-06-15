<?php

class PostFinance_Payment_Controller_Abstract extends Mage_Core_Controller_Front_Action
{
    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    protected function getConfig()
    {
        return Mage::getModel('postfinance/config');
    }

    /**
     * Return order instance loaded by increment id
     *
     * @param null $quoteId int
     * @return mixed
     */
    protected function _getOrder($quoteId = null)
    {
        if (empty($this->_order)) {
            if (is_null($quoteId)) {
                $quoteId = $this->getRequest()->getParam('orderID');
            }
            $devPrefix = $this->getConfig()->getConfigData('devprefix');
            if ($devPrefix == substr($quoteId, 0, strlen($devPrefix))) {
                $quoteId = substr($quoteId, strlen($devPrefix));
            }
            $this->_order = Mage::getModel('sales/order')->getCollection()
                ->addFieldToFilter('quote_id', $quoteId)
                ->getFirstItem();
        }
        return $this->_order;
    }

    /**
     * Get singleton with Checkout by postfinance Api
     *
     * @return PostFinance_Payment_Model_Payment_Abstract
     */
    protected function _getApi()
    {
        if (!is_null($this->getRequest()->getParam('orderID'))):
            return $this->_getOrder()->getPayment()->getMethodInstance();
        else:
            return Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance();
        endif;
    }

    /**
     * get payment helper
     * 
     * @return PostFinance_Payment_Helper_Payment
     */
    protected function getPaymentHelper()
    {
        return Mage::helper('postfinance/payment');
    }
    
    /**
     * get direct link helper
     * 
     * @return PostFinance_Payment_Helper_Payment
     */
    protected function getDirectlinkHelper()
    {
        return Mage::helper('postfinance/directlink');
    }

    /**
     * Validation of incoming postfinance data
     *
     * @return bool
     */
    protected function _validatePostFinanceData()
    {
        $params = $this->getRequest()->getParams();

        $secureKey = $this->_getApi()->getConfig()->getShaInCode();
        $secureSet = $this->getPaymentHelper()->getSHAInSet($params, $secureKey);

        /** @var $helper PostFinance_Payment_Helper_Data */
        $helper = Mage::helper('postfinance');
        $helper->log($helper->__("Incoming PostFinance Feedback\n\nRequest Path: %s\nParams: %s\n",
            $this->getRequest()->getPathInfo(),
            serialize($this->getRequest()->getParams())
        ));
        
        if ($this->getPaymentHelper()->shaCryptValidation($secureSet, $params['SHASIGN']) !== true) {
            $this->_getCheckout()->addError($this->__('Hash is not valid'));
            return false;
        }

        /** @var $order Mage_Sales_Model_Order */
        $order = $this->_getOrder();
        if (!$order->getId()){
            $this->_getCheckout()->addError($this->__('Order is not valid'));
            return false;
        }

        return true;
    }

    public function isJsonRequested($params)
    {
        if (array_key_exists('RESPONSEFORMAT', $params) && $params['RESPONSEFORMAT'] == 'JSON') {
                return true;
        }
        return false;
    }
}
