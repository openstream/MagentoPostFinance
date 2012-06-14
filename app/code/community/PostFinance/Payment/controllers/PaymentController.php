<?php

class PostFinance_Payment_PaymentController extends PostFinance_Payment_Controller_Abstract
{
    /**
     * Load place from layout to make POST on postfinance
     */
    public function placeformAction()
    {
        $lastIncrementId = $this->_getCheckout()->getLastRealOrderId();

        if ($lastIncrementId) {
            /* @var $order Mage_Sales_Model_Order */
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($lastIncrementId);

            // update transactions, order state and add comments
            /* @var $payment Mage_Sales_Model_Order_Payment */
            $payment = $order->getPayment();
            $payment->setTransactionId($order->getQuoteId())
                    ->setIsTransactionClosed(false)
                    ->addTransaction("authorization", null, true, $this->__("Process outgoing transaction"));
   
            if ($order->getId()) {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    Mage::helper('postfinance')->__('Start PostFinance processing')
                );
                $order->save();
            }
        }

        $this->_getCheckout()->getQuote()->setIsActive(false)->save();
        $this->_getCheckout()->setPostFinanceQuoteId($this->_getCheckout()->getQuoteId());
        $this->_getCheckout()->setPostFinanceLastSuccessQuoteId($this->_getCheckout()->getLastSuccessQuoteId());
        $this->_getCheckout()->clear();

        $this->loadLayout();
        $this->renderLayout();
    }
    
    /**
     * Render 3DSecure response HTML_ANSWER
     */
    public function placeform3dsecureAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Display our pay page, need to postfinance payment with external pay page mode     *
     */
    public function paypageAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * when payment gateway accept the payment, it will land to here
     * need to change order status as processed postfinance
     * update transaction id
     *
     */
    public function acceptAction()
    {
        if ($this->isJsonRequested($this->getRequest()->getParams())) {
            $result = array('result' => 'success', 'alias' => $this->_request->getParam('Alias'));
            return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }

        try {
            $this->checkRequestValidity();
            $this->getPaymentHelper()->applyStateForOrder(
                $this->_getOrder(),
                $this->getRequest()->getParams()
            );
        } catch (Exception $e) {
            /* @var $helper PostFinance_Payment_Helper_Data */
            $helper = Mage::helper('postfinance');
            $helper->log($helper->__("Exception in acceptAction: ".$e->getMessage()));
            $this->getPaymentHelper()->refillCart($this->_getOrder());
            $this->_redirect('checkout/cart');
            return false;
        }
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * the payment result is uncertain
     * exception status can be 52 or 92
     * need to change order status as processing postfinance
     * update transaction id
     *
     */
    public function exceptionAction()
    {
        $params = $this->getRequest()->getParams();

        if ($this->isJsonRequested($params)) {
            Mage::helper('postfinance')->log(var_export($params, true));
            $errors = array();

            foreach ($params as $key => $value) {
                if (stristr($key, 'error') && 0 != $value) {
                    $errors[] = $value;
                }
            }

            $result = array('result' => 'failure', 'errors' => $errors);
            return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }

        try {
            $this->checkRequestValidity();
            $this->getPaymentHelper()->handleException(
                $this->_getOrder(),
                $this->getRequest()->getParams()
            );
        } catch (Exception $e) {
            $this->_redirect('checkout/cart');
            return false;
        }
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * when payment got decline
     * need to change order status to cancelled
     * take the user back to shopping cart
     *
     */
    public function declineAction()
    {
        try {
            $this->checkRequestValidity();
            $this->_getCheckout()->setQuoteId($this->_getCheckout()->getPostFinanceQuoteId());
            $this->getPaymentHelper()->declineOrder(
                $this->_getOrder(),
                $this->getRequest()->getParams()
            );
        } catch (Exception $e) { }

        $this->getPaymentHelper()->refillCart($this->_getOrder());

        $message = Mage::helper('postfinance')->__('Your payment information was declined. Please select another payment method.');
        Mage::getSingleton('core/session')->addNotice($message);

        $this->_redirect('checkout/onepage');
    }

    /**
     * when user cancel the payment
     * change order status to cancelled
     * need to redirect user to shopping cart
     *
     * @return PostFinance_Payment_ApiController
     */
    public function cancelAction()
    {
        try {
            $params = $this->getRequest()->getParams();
            $this->checkRequestValidity();
            $this->_getCheckout()->setQuoteId($this->_getCheckout()->getPostFinanceQuoteId());
            $this->getPaymentHelper()->cancelOrder(
                $this->_getOrder(),
                $params,
                Mage_Sales_Model_Order::STATE_CANCELED,
                Mage::helper('postfinance')->__(
                    'Order canceled on PostFinance side. Status: %s, Payment ID: %s.',
                    Mage::helper('postfinance')->getStatusText($params['STATUS']),
                    $params['PAYID'])
            );
        } catch (Exception $e) { }
        if (false == $this->_getOrder()->getId()) {
            $this->_order = null;
            $this->_getOrder($this->_getCheckout()->getLastQuoteId());
        }
        
        $this->getPaymentHelper()->refillCart($this->_getOrder());        
        $this->_redirect('checkout/cart');
    }
    
    /**
     * when user cancel the payment and press on button "Back to Catalog" or "Back to Merchant Shop" in Orops
     *
     * @return PostFinance_Payment_ApiController
     */
    public function continueAction()
    {
        $order = Mage::getModel('sales/order')->load(
            $this->_getCheckout()->getLastOrderId()
        );
        $this->getPaymentHelper()->refillCart($order);
        $redirect = $this->getRequest()->getParam('redirect');
        if ($redirect == 'catalog'): //In Case of "Back to Catalog" Button in postfinance
            $this->_redirect('/'); 
        else: //In Case of Cancel Auto-Redirect or "Back to Merchant Shop" Button
            $this->_redirect('checkout/cart'); 
        endif;
    }
    
    /*
     * Check the validation of the request from postfinance
     */
    protected function checkRequestValidity()
    {
        if (!$this->_validatePostFinanceData()) {
            throw new Exception("Hash is not valid");
        }
    }

    /**
     * Return json encoded hash
     */
    public function generateHashAction()
    {
        $config = Mage::getModel('postfinance/config');

        $data = array(
            'ACCEPTURL'     => $config->getAcceptUrl(),
            'ALIAS'         => $this->_request->getParam('alias'),
            'BRAND'         => $this->_request->getParam('brand'),
            'EXCEPTIONURL'  => $config->getExceptionUrl(),
            'ORDERID'       => $this->_request->getParam('orderid'),
            'PARAMPLUS'     => $this->_request->getParam('paramplus'),
            'PSPID'         => $config->getPSPID(),
        );

        $secret = $config->getShaOutCode();
        $raw = null;
        foreach ($data as $key => $value) {
            $raw .= sprintf('%s=%s%s', $key, $value, $secret);
        }

        $result = array('hash' => Mage::helper('postfinance/payment')->shaCrypt($raw));
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    public function saveAliasAction()
    {
        $alias = $this->_request->getParam('alias');
        if (0 < strlen($alias)) {
            $payment = $this->_getCheckout()->getQuote()->getPayment();
            $payment->setAdditionalInformation('alias', $alias);
            $payment->setDataChanges(true);
            $payment->save();
            Mage::helper('postfinance')->log('saved alias ' . $alias . ' for quote #' . $this->_getCheckout()->getQuote()->getId());
        } else {
            Mage::log('did not save alias due to empty alias:', null, 'postfinance_alias.log');
            Mage::log($this->_request->getParams(), null, 'postfinance_alias.log');
        }
    }

    public function saveCcBrandAction()
    {
        $brand = $this->_request->getParam('brand');
        $cn = $this->_request->getParam('cn');

        $payment = $this->_getCheckout()->getQuote()->getPayment();
        $payment->setAdditionalInformation('CC_BRAND', $brand);
        $payment->setAdditionalInformation('CC_CN', $cn);
        $payment->setDataChanges(true);
        $payment->save();
        Mage::helper('postfinance')->log('saved cc brand ' . $brand . ' for quote #' . $this->_getCheckout()->getQuote()->getId());
        $this->getResponse()->sendHeaders();
    }
}
