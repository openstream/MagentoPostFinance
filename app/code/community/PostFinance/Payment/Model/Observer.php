<?php

class PostFinance_Payment_Model_Observer
{
    /**
     * Get one page checkout model
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    public function getHelper($name=null)
    {
        if (is_null($name)) {
            return Mage::helper('postfinance');
        }
        return Mage::helper('postfinance/' . $name);
    }

    /**
     * Trigger PostFinance payment
     *
     * @param $observer Varien_Event_Observer
     * @throws Mage_Core_Exception
     */
    public function checkoutTypeOnepageSaveOrderBefore($observer)
    {
        /** @var $quote Mage_Sales_Model_Quote */
        $quote = $observer->getQuote();
        $order = $observer->getOrder();
        $code = $quote->getPayment()->getMethodInstance()->getCode();

        try {
            if ('postfinance_cc' == $code && $quote->getPayment()->getMethodInstance()->hasBrandAliasInterfaceSupport($quote->getPayment(), 1)) {
                $this->confirmCcPayment($order, $quote);
            }
        } catch (Exception $e) {
            $quote->setIsActive(true);
            $this->getOnepage()->getCheckout()->setGotoSection('payment');
            throw new Mage_Core_Exception($e->getMessage());
        }
    }

    /**
     * @param $observer Varien_Event_Observer
     */
    public function salesModelServiceQuoteSubmitSuccess($observer)
    {
        /** @var $quote Mage_Sales_Model_Quote */
        $quote = $observer->getQuote();
        if (true === $this->isCheckoutWithCcOrDd($quote->getPayment()->getMethodInstance()->getCode())) {
            $quote = $observer->getQuote();
            $quote->getPayment()
                ->setAdditionalInformation('checkoutFinishedSuccessfully', true)
                ->save();
        }
    }

    /**
     * Set order status for orders with PostFinance payment
     *
     * @param $observer Varien_Event_Observer
     */
    public function checkoutTypeOnepageSaveOrderAfter($observer)
    {
        /** @var $quote Mage_Sales_Model_Quote */
        $quote = $observer->getQuote();
        if (true === $this->isCheckoutWithCcOrDd($quote->getPayment()->getMethodInstance()->getCode())) {
            $order = $observer->getOrder();
    
            /* if there was no error */
            if (true === $quote->getPayment()->getAdditionalInformation('checkoutFinishedSuccessfully')) {
                $_response = $quote->getPayment()->getAdditionalInformation('postfinance_response');
                if ($_response) {
                    Mage::helper('postfinance/payment')->applyStateForOrder($order, $_response);
                }
            } else {
                $this->handleFailedCheckout($quote, $order);
            }
        }
    }

    /**
     * @param $observer Varien_Event_Observer
     */
    public function salesModelServiceQuoteSubmitFailure($observer)
    {
        $quote = $observer->getQuote();
        if (true === $this->isCheckoutWithCcOrDd($quote->getPayment()->getMethodInstance()->getCode())) {
            $this->handleFailedCheckout(
                $observer->getQuote(),
                $observer->getOrder()
            );
        }
    }

    public function handleFailedCheckout($quote, $order)
    {
        if (true === $this->isCheckoutWithCcOrDd($quote->getPayment()->getMethodInstance()->getCode())) {
            $_response = $quote->getPayment()->getAdditionalInformation('postfinance_response');
            if ($_response) {
                $this->getHelper()->log('Cancel PostFinance Payment because Order Save Process failed.');
                
                //Try to cancel order only if the payment was ok
                if (Mage::helper('postfinance/payment')->isPaymentAccepted($_response['STATUS'])) {
                    if (true === $this->getHelper('payment')->isPaymentAuthorizeType($_response['STATUS'])) { //do a void
                        $params = array (
                            'OPERATION' => PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_DELETE_AUTHORIZE_AND_CLOSE,
                            'ORDERID' => Mage::getSingleton('postfinance/config')->getConfigData('devprefix').$quote->getId(),
                            'AMOUNT' => round($quote->getGrandTotal() * 100)
                        );
                    }
                    
                    if (true === $this->getHelper('payment')->isPaymentCaptureType($_response['STATUS'])) { //do a refund
                        $params = array (
                            'OPERATION' => PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_FULL,
                            'ORDERID' => Mage::getSingleton('postfinance/config')->getConfigData('devprefix').$quote->getId(),
                            'AMOUNT' => round($quote->getGrandTotal() * 100)
                        );
                    }
                    $url = Mage::getModel('postfinance/config')->getDirectLinkGatewayOrderPath();
                    Mage::getSingleton('postfinance/api_directlink')->performRequest($params, $url);
                }
            }
        }
    }

    /**
     * @param $quote Mage_Sales_Model_Quote
     * @return mixed
     */
    protected function getQuoteCurrency($quote)
    {
        if ($quote->hasForcedCurrency()){
            return $quote->getForcedCurrency()->getCode();
        } else {
            return $quote->getStore()->getCurrentCurrency()->getCode();
        }
    }

    /**
     * @param $order Mage_Sales_Model_Order
     * @param $quote Mage_Sales_Model_Quote
     */
    public function confirmCcPayment($order, $quote)
    {
        /** @var $config PostFinance_Payment_Model_Config */
        $config = Mage::getSingleton('postfinance/config');
        $alias = $quote->getPayment()->getAdditionalInformation('alias');

        $requestParams = array(
            'ALIAS'            => $alias,
            'AMOUNT'           => round($quote->getGrandTotal() * 100),
            'CURRENCY'         => $this->getQuoteCurrency($quote),
            'OPERATION'        => $this->_getPaymentAction($quote),
            'ORDERID'          => $config->getConfigData('devprefix').$quote->getId(),
            'REMOTE_ADDR'      => $order->getRemoteIp(),
            'EMAIL'            => $order->getCustomerEmail()
        );
        
        $requestParams3ds = array();
        if ($config->get3dSecureIsActive()) {
            $requestParams3ds = array(
                'FLAG3D'           => 'Y',
                'WIN3DS'           => PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_DIRECTLINK_WIN3DS,
                'LANGUAGE'         => Mage::app()->getLocale()->getLocaleCode(),
                'HTTP_ACCEPT'      => '*/*',
                'HTTP_USER_AGENT'  => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)',
                'ACCEPTURL'        => $config->getPostFinanceUrl('accept'),
                'DECLINEURL'       => $config->getPostFinanceUrl('decline'),
                'EXCEPTIONURL'     => $config->getPostFinanceUrl('exception')
            );
        }
        $requestParams = array_merge($requestParams, $requestParams3ds);
        $this->performDirectLinkRequest($quote, $requestParams);
    }

    /**
     * @param $quote Mage_Sales_Model_Quote
     * @param $params array
     * @throws Mage_Core_Exception
     */
    public function performDirectLinkRequest($quote, $params)
    {
        /** @var $helper PostFinance_Payment_Helper_Data */
        $helper = Mage::helper('postfinance');
        $url = Mage::getModel('postfinance/config')->getDirectLinkGatewayOrderPath();
        $helper->log('DirectLink Request:'."\n\t".'URL: '.$url."\n\t".'Params:'."\n".var_export($params, true));
        $response = Mage::getSingleton('postfinance/api_directlink')->performRequest($params, $url);
        if (Mage::helper('postfinance/payment')->isPaymentFailed($response['STATUS'])) {
            $helper->log(sprintf('PostFinance Payment Failed with error message "%s"', $response['NCERRORPLUS']));
            throw new Mage_Core_Exception('PostFinance Payment failed');
        }
        $helper->log('DirectLink Response:'."\n".var_export($response, true));
        $quote->getPayment()->setAdditionalInformation('postfinance_response', $response)->save();
    }

    /**
     * Check if checkout was made with PostFinance CreditCart
     *
     * @param $code string
     * @return boolean
     */
    protected function isCheckoutWithCcOrDd($code)
    {
        return 'postfinance_cc' == $code;
    }
    
    /**
     * get payment operation code
     * 
     * @param Mage_Sales_Model_Order $order 
     *
     * @return string
     */
    public function _getPaymentAction($order)
    {
        if ('authorize_capture' == Mage::getModel('postfinance/config')->getPaymentAction()) {
            return PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZE_CAPTURE_ACTION;
        } else {
            return PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZE_ACTION;
        }
    }
}
