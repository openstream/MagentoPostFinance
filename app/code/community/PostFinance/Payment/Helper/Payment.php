<?php

class PostFinance_Payment_Helper_Payment extends Mage_Core_Helper_Abstract
{
    const HASH_ALGO = 'sha1';

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function getConfig()
    {
        return Mage::getSingleton('postfinance/config');
    }

    /**
     * Crypt Data by SHA1 ctypting algorithm by secret key
     *
     * @param array $data
     * @return string
     */
    public function shaCrypt($data)
    {
        if (is_array($data)) {
            return hash(self::HASH_ALGO, implode("", $data));
        }if (is_string($data)) {
            return hash(self::HASH_ALGO, $data);
        } else {
            return "";
        }
    }

    /**
     * Check hash crypted by SHA1 with existing data
     *
     * @param array $data
     * @param string $hash
     * @return bool
     */
    public function shaCryptValidation($data, $hash)
    {
        if (is_array($data)) {
            $data = implode("", $data);
        }

        $hashUtf8 = strtoupper(hash(self::HASH_ALGO, $data));
        $hashNonUtf8 = strtoupper(hash(self::HASH_ALGO, utf8_decode($data)));

        /* @var $helper PostFinance_Payment_Helper_Data */
        $helper = Mage::helper('postfinance');
        $helper->log($helper->__("Module Secureset: %s", $data));

        if ($this->compareHashes($hash, $hashUtf8)) {
            return true;
        } else {
            $helper->log($helper->__("Trying again with non-utf8 secureset"));
            return $this->compareHashes($hash, $hashNonUtf8);
        }
    }

    private function compareHashes($expected, $actual)
    {
        /* @var $helper PostFinance_Payment_Helper_Data */
        $helper = Mage::helper('postfinance');
        $helper->log($helper->__("Checking hashes\nHashed String by Magento: %s\nHashed String by PostFinance: %s",
            $expected,
            $actual
        ));

        if ($expected == $actual) {
            $helper->log("Successful validation");
            return true;
        }

        return false;
    }

    /**
     * Return set of data which is ready for SHA crypt
     *
     * @param array $params
     * @param string $SHAkey
     *
     * @return string
     */
    public function getSHAInSet($params, $SHAkey)
    {
        $params = $this->prepareParamsAndSort($params);
        $plainHashString = "";
        foreach ($params as $paramSet):
            if ($paramSet['value'] == '' || $paramSet['key'] == 'SHASIGN') continue;
            $plainHashString .= strtoupper($paramSet['key'])."=".$paramSet['value'].$SHAkey;
        endforeach;
        return $plainHashString;
    }
    
    /**
     * Return prepared and sorted array for SHA Signature Validation
     *
     * @param array $params
     *
     * @return array
     */
    protected function prepareParamsAndSort($params)
    {
        unset($params['CardNo']);
        unset($params['Brand']);
        unset($params['SHASign']);

        $params = array_change_key_case($params,CASE_UPPER);
        
        //PHP ksort take care about "_", PostFinance not
        $sortedParams = array();
        foreach ($params as $key => $value):
            $sortedParams[str_replace("_", "", $key)] = array('key' => $key, 'value' => utf8_encode($value));
        endforeach;
        ksort($sortedParams);
        return $sortedParams;
    }
    
    /*
     * Get SHA-1-IN hash for postfinance-authentification
     * 
     * All Parameters have to be alphabetically, UPPERCASE
     * Empty Parameters shouldn't appear in the secure string
     *
     * @param array  $formFields
     * @param string $shaCode
     * 
     * @return string
     */
    public function getSHASign($formFields, $shaCode = null)
    {
        if (is_null($shaCode)) {
            $shaCode = Mage::getModel('postfinance/config')->getShaOutCode();
        }
        $formFields = array_change_key_case($formFields, CASE_UPPER);

        unset($formFields['ORDERSHIPMETH']);

        uksort($formFields, 'strnatcasecmp');
        $plainHashString = '';
        foreach ($formFields as $formKey => $formVal) {
            if ('' === $formVal || $formKey == 'SHASIGN') {
                continue;
            }
            $plainHashString .= strtoupper($formKey) . '=' . $formVal . $shaCode;
        }

        return $plainHashString;
    }

    /**
     * We get some CC info from postfinance, so we must save it
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $ccInfo
     *
     * @return PostFinance_Payment_ApiController
     */
    public function _prepareCCInfo($order, $ccInfo)
    {
        if(isset($ccInfo['CN'])){
            $order->getPayment()->setCcOwner($ccInfo['CN']);
        }
        
        if(isset($ccInfo['CARDNO'])){
            $order->getPayment()->setCcNumberEnc($ccInfo['CARDNO']);
            $order->getPayment()->setCcLast4(substr($ccInfo['CARDNO'], -4));
        }

        if(isset($ccInfo['ED'])){
            $order->getPayment()->setCcExpMonth(substr($ccInfo['ED'], 0, 2));
            $order->getPayment()->setCcExpYear(substr($ccInfo['ED'], 2, 2));
        }

        return $this;
    }

    public function isPaymentAccepted($status)
    {
        return in_array($status, array(
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_WAITING,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_UNKNOWN,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AWAIT_CUSTOMER_PAYMENT,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_REQUESTED,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_PROCESSING,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_UNCERTAIN,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_WAITING_FOR_IDENTIFICATION
        ));
    }
    
    public function isPaymentAuthorizeType($status)
    {
        return in_array($status, array(
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_WAITING,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_UNKNOWN,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AWAIT_CUSTOMER_PAYMENT
        ));
    }
    
    public function isPaymentCaptureType($status)
    {
        return in_array($status, array(
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_REQUESTED,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_PROCESSING,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_UNCERTAIN
        ));
    }

    public function isPaymentFailed($status)
    {
        return false == $this->isPaymentAccepted($status);
    }

    /**
     * apply postfinance state for order
     * 
     * @param Mage_Sales_Model_Order $order  Order
     * @param array                  $params Request params
     *
     * @return void
     */
    public function applyStateForOrder($order, $params)
    {
        /**
         * OpenInvoiceDe should always have status code 41, which is a final state in this case
         */
        if ($order->getPayment()->getMethodInstance()->getCode() == 'postfinance_openInvoiceDe'
            && $params['STATUS'] == PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AWAIT_CUSTOMER_PAYMENT
        ) {
            $params['STATUS'] = PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_OPEN_INVOICE_DE_PROCESSED;
        }

        switch ($params['STATUS']) {
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_INVALID :
                break;
                
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_WAITING_FOR_IDENTIFICATION : //3D-Secure
                $this->waitOrder($order, $params);
                break;
                
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED :
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_WAITING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_UNKNOWN:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AWAIT_CUSTOMER_PAYMENT:
                $this->acceptOrder($order, $params);
                break;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_REQUESTED:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_PROCESSING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_UNCERTAIN:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_OPEN_INVOICE_DE_PROCESSED:
                $this->acceptOrder($order, $params, 1);
                break;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTH_REFUSED:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_CANCELED_BY_CUSTOMER:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_REFUSED:
                $this->declineOrder($order, $params);
                break;
            default:
                //all unknown transaction will accept as exceptional
                $this->handleException($order, $params);
        }
    }

    /**
     * Process success action by accept url
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $params
     * @param int $instantCapture
     * @throws Exception
     */
    public function acceptOrder($order, $params, $instantCapture = 0)
    {
        $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
        $this->_prepareCCInfo($order, $params);
        $this->setPaymentTransactionInformation($order->getPayment(),$params);
        
        if ($transaction = Mage::helper('postfinance/payment')->getTransactionByTransactionId($order->getQuoteId())) {
            $transaction->setTxnId($params['PAYID'])->save();
        }

        try {
            if (($this->getConfig()->getConfigData('payment_action') == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE 
                || $instantCapture)
                && $params['STATUS'] != PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AWAIT_CUSTOMER_PAYMENT) {
                $this->_processDirectSale($order, $params, $instantCapture);
            } else {
                $this->_processAuthorize($order, $params);
            }
        } catch (Exception $e) {
            $this->_getCheckout()->addError(Mage::helper('postfinance')->__('Order can not be saved.'));
            throw $e;
        }
    }
    
    /**
     * Set Payment Transaction Information
     *
     * @param Mage_Sales_Model_Order_Payment $payment Sales Payment Model
     * @param array                  $params Request params
     */
    protected function setPaymentTransactionInformation($payment, $params)
    {
        $payment->setTransactionId($params['PAYID']);
        $code = $payment->getMethodInstance()->getCode();

        if (in_array($code, array('postfinance_cc', 'postfinance_directDebit'))) {
            $payment->setIsTransactionClosed(false);
            $payment->addTransaction("authorization", null, true, $this->__("Process outgoing transaction"));
            $payment->setLastTransId($params['PAYID']);
            if (isset($params['HTML_ANSWER'])) $payment->setAdditionalInformation('HTML_ANSWER', $params['HTML_ANSWER']);
        }

        $payment->setAdditionalInformation('paymentId', $params['PAYID']);
        $payment->setAdditionalInformation('status', $params['STATUS']);
        $payment->setIsTransactionClosed(true);
        $payment->setDataChanges(true);
        $payment->save();
    }

    /**
     * Process cancel action by cancel url
     *
     * @param Mage_Sales_Model_Order $order   Order
     * @param array                  $params  Request params
     * @param string                 $status  Order status
     * @param string                 $comment Order comment
     */
    public function cancelOrder($order, $params, $status, $comment)
    {
        try{
            Mage::register('postfinance_auto_void', true); //Set this session value to true to allow cancel
            $order->cancel();
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, $status, $comment);
            $order->save();            
            $this->setPaymentTransactionInformation($order->getPayment(),$params);
        } catch(Exception $e) {
            $this->_getCheckout()->addError(Mage::helper('postfinance')->__('Order can not be canceled for system reason.'));
            throw $e;
        }
    }

    /**
     * Process decline action by postfinance decline url
     *
     * @param Mage_Sales_Model_Order $order  Order
     * @param array                  $params Request params
     */
    public function declineOrder($order, $params)
    {
        try{
            Mage::register('postfinance_auto_void', true); //Set this session value to true to allow cancel
            $order->cancel();
            $order->setState(
                Mage_Sales_Model_Order::STATE_CANCELED,
                Mage_Sales_Model_Order::STATE_CANCELED,
                Mage::helper('postfinance')->__(
                    'Order declined on PostFinance side. PostFinance status: %s, Payment ID: %s.',
                    Mage::helper('postfinance')->getStatusText($params['STATUS']),
                    $params['PAYID']
                )
            );
            $order->save();
            $this->setPaymentTransactionInformation($order->getPayment(),$params);
        } catch(Exception $e) {
            $this->_getCheckout()->addError(Mage::helper('postfinance')->__('Order can not be canceled for system reason.'));
            throw $e;
        }
    }
    
    /**
     * Process decline action by postfinance decline url
     *
     * @param Mage_Sales_Model_Order $order  Order
     * @param array                  $params Request params
     */
    public function waitOrder($order, $params)
    {
        try {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('postfinance')->__(
                    'Order is waiting for PostFiance confirmation of 3D-Secure. PostFinance status: %s, Payment ID: %s.',
                    Mage::helper('postfinance')->getStatusText($params['STATUS']),
                    $params['PAYID']
                )
            );
            $order->save();
            $this->setPaymentTransactionInformation($order->getPayment(), $params);
        } catch(Exception $e) {
            $this->_getCheckout()->addError(Mage::helper('postfinance')->__('Error during 3D-Secure processing of PostFinance. Error: %s', $e->getMessage()));
            throw $e;
        }
    }

    /**
     * Process exception action by postfinance exception url
     *
     * @param Mage_Sales_Model_Order $order  Order
     * @param array                  $params Request params
     */
    public function handleException($order, $params)
    {
        $exceptionMessage = $this->getPaymentExceptionMessage($params['STATUS']);

        if (!empty($exceptionMessage)) {
            try{
                $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
                $this->_prepareCCInfo($order, $params);
                $order->getPayment()->setLastTransId($params['PAYID']);
                //to send new order email only when state is pending payment
                if ($order->getState()==Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                    $order->sendNewOrderEmail();
                }
                $order->addStatusHistoryComment($exceptionMessage);
                $order->save();
                $this->setPaymentTransactionInformation($order->getPayment(),$params);
            } catch(Exception $e) {
                $this->_getCheckout()->addError(Mage::helper('postfinance')->__('Order can not be saved for system reason.'));
            }
        } else {
            $this->_getCheckout()->addError(Mage::helper('postfinance')->__('An unknown exception occured.'));
        }
    }
    
    /**
     * Get Payment Exception Message
     *
     * @param int $postfinance_status Request PostFinance Status
     */
    protected function getPaymentExceptionMessage($postfinance_status)
    {
        $exceptionMessage = '';
        switch($postfinance_status) {
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_UNCERTAIN :
                $exceptionMessage = Mage::helper('postfinance')->__(
                    'A technical problem arose during payment process, giving unpredictable result. PostFinance status: %s.',
                    Mage::helper('postfinance')->getStatusText($postfinance_status)
                );
                break;
            default:
                $exceptionMessage = Mage::helper('postfinance')->__(
                    'An unknown exception was thrown in the payment process. PostFinance status: %s.',
                    Mage::helper('postfinance')->getStatusText($postfinance_status)
                );
        }
        return $exceptionMessage;
    }

    /**
     * Process Configured Payment Action: Direct Sale, create invoce if state is Pending
     *
     * @param Mage_Sales_Model_Order $order  Order
     * @param array                  $params Request params
     */
    protected function _processDirectSale($order, $params, $instantCapture = 0)
    {
        Mage::register('postfinance_auto_capture', true);
        $status = $params['STATUS'];
        if ($status == PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AWAIT_CUSTOMER_PAYMENT) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('postfinance')->__('Waiting for the payment of the customer')
            );
            $order->save();
        } elseif ($status == PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_WAITING) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('postfinance')->__('Authorization waiting from PostFinance')
            );
            $order->save();
        } elseif ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT
            || $instantCapture
        ) {
            if ($status == PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED) {
                if ($order->getStatus() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                    $order->setState(
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        Mage::helper('postfinance')->__('Processed by PostFinance')
                    );
                }
            } else {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PROCESSING,
                    Mage_Sales_Model_Order::STATE_PROCESSING,
                    Mage::helper('postfinance')->__('Processed by PostFinance')
                );
            }

            if (!$order->getInvoiceCollection()->getSize()) {
                $invoice = $order->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
                $invoice->getOrder()->setIsInProcess(true);
                $invoice->save();

                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

                    /*
                     * If the payment method is a redirect-payment-method send the email
                     * In any other case Magento sends an email automatically in Mage_Checkout_Model_Type_Onepage::saveOrder
                     */
                    if ($this->isRedirectPaymentMethod($order) === true 
                        && $order->getEmailSent() !== '1') {
                        $order->sendNewOrderEmail();
                    }
            }
        } else {
            $order->save();
        }
    }


    /**
     * Process Configured Payment Actions: Authorized, Default operation
     * just place order
     *
     * @param Mage_Sales_Model_Order $order  Order
     * @param array                  $params Request params
     */
    protected function _processAuthorize($order, $params)
    {
        $status = $params['STATUS'];
        if ($status == PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AWAIT_CUSTOMER_PAYMENT) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('postfinance')->__('Waiting for payment. PostFinance status: %s.', Mage::helper('postfinance')->getStatusText($status))
            );
        } elseif ($status ==  PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_WAITING) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('postfinance')->__('Authorization uncertain. PostFinance status: %s.', Mage::helper('postfinance')->getStatusText($status))
            );
        } else {
            if ($this->isRedirectPaymentMethod($order) === true 
                && $order->getEmailSent() !== '1') {
                $order->sendNewOrderEmail();
            }

            $payId = $params['PAYID'];
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage::helper('postfinance')->__('Processed by PostFinance. Payment ID: %s. PostFinance status: %s.', $payId, Mage::helper('postfinance')->getStatusText($status))
            );
        }
        $order->save();
    }

    /**
     * Fetches transaction with given transaction id
     *
     * @param string $txnId
     * @return mixed Mage_Sales_Model_Order_Payment_Transaction | boolean
     */
    public function getTransactionByTransactionId($transactionId)
    {
        if (!$transactionId) {
            return;
        }
        $transaction = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('txn_id', $transactionId)
            ->getLastItem();
        if (is_null($transaction->getId())) return false;
        $transaction->getOrderPaymentObject();
        return $transaction;
    }

    /**
     * refill cart
     * 
     * @param Mage_Sales_Model_Order $order 
     *
     * @return void
     */
    public function refillCart($order)
    {
        // add items
        $cart = Mage::getSingleton('checkout/cart');
        foreach ($order->getItemsCollection() as $item) {
            try {
                $cart->addOrderItem($item);
            } catch (Exception $e) {
                Mage::log($e->getMessage());
            }
        }
        $cart->save();

        // add coupon code
        $coupon = $order->getCouponCode();
        $session = Mage::getSingleton('checkout/session');
        if (false == is_null($coupon)) {
            $session->getQuote()->setCouponCode($coupon)->save();
        }
    }
    
    /**
     * Save PostFinance Status to Payment
     * 
     * @param Mage_Sales_Model_Order_Payment $payment 
     * @param array $params PostFinance-Response
     *
     * @return void
     */
    public function savePostFinanceStatusToPayment(Mage_Sales_Model_Order_Payment $payment, $params)
    {
        $payment
            ->setAdditionalInformation('status', $params['STATUS'])
            ->save();
    }

    /**
     * get alias or generate a new one
     *
     * alias has length 16 and consists of quote creation date, a separator, and the quote id
     * to make sure we have the full quote id we shorten the creation date accordingly
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return string
     */
    public function getAlias($quote)
    {
        $alias = $quote->getPayment()->getAdditionalInformation('alias');
        if (0 == strlen($alias)) {
            /* turn createdAt into format MMDDHHii */
            $createdAt = substr(str_replace(array(':', '-', ' '), '', $quote->getCreatedAt()), 4, -2);
            $quoteId   = $quote->getEntityId();

            /* shorten createdAt, if we would exceed maximum length */
            $maxAliasLength = 16;
            $separator = '99';
            $maxCreatedAtLength = $maxAliasLength - strlen($quoteId) - strlen($separator);
            $alias = substr($createdAt, 0, $maxCreatedAtLength) . $separator . $quoteId;
        }
        return $alias;
    }
    
    /**
     * Check is payment method is a redirect method
     * 
     * @param Mage_Sales_Model_Order $order
     */
    protected function isRedirectPaymentMethod($order)
    {
        $method = $order->getPayment()->getMethodInstance();
        
        if ($method 
            && $method->getOrderPlaceRedirectUrl() != '' //Magento returns ''
            && $method->getOrderPlaceRedirectUrl() !== false) //PostFinance return false
            return true;
        else
            return false;
    }
}
