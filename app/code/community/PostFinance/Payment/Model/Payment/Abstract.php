<?php

class PostFinance_Payment_Model_Payment_Abstract extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'postfinance';
    protected $_formBlockType = 'postfinance/form';
    protected $_infoBlockType = 'postfinance/info';

     /**
     * Magento Payment Behaviour Settings
     */
    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;
    protected $_isInitializeNeeded      = true;

    /** 
     * template modes
     */
    const TEMPLATE_POSTFINANCE            = 'postfinance';
    const TEMPLATE_MAGENTO          = 'magento';

    /**
     * response status
     */
    const POSTFINANCE_INVALID                             = 0;
    const POSTFINANCE_PAYMENT_CANCELED_BY_CUSTOMER        = 1;
    const POSTFINANCE_AUTH_REFUSED                        = 2;
    
    const POSTFINANCE_ORDER_SAVED                         = 4;
    const POSTFINANCE_AWAIT_CUSTOMER_PAYMENT              = 41;
    const POSTFINANCE_OPEN_INVOICE_DE_PROCESSED           = 41000001;
    const POSTFINANCE_WAITING_FOR_IDENTIFICATION          = 46;
    
    const POSTFINANCE_AUTHORIZED                          = 5;
    const POSTFINANCE_AUTHORIZED_WAITING                  = 51;
    const POSTFINANCE_AUTHORIZED_UNKNOWN                  = 52;
    const POSTFINANCE_STAND_BY                            = 55;
    const POSTFINANCE_PAYMENTS_SCHEDULED                  = 56;
    const POSTFINANCE_AUTHORIZED_TO_GET_MANUALLY          = 59;
    
    const POSTFINANCE_VOIDED                              = 6;
    const POSTFINANCE_VOID_WAITING                        = 61;
    const POSTFINANCE_VOID_UNCERTAIN                      = 62;
    const POSTFINANCE_VOID_REFUSED                        = 63;
    const POSTFINANCE_VOIDED_ACCEPTED                     = 64;
    
    const POSTFINANCE_PAYMENT_DELETED                     = 7;
    const POSTFINANCE_PAYMENT_DELETED_WAITING             = 71;
    const POSTFINANCE_PAYMENT_DELETED_UNCERTAIN           = 72;
    const POSTFINANCE_PAYMENT_DELETED_REFUSED             = 73;
    const POSTFINANCE_PAYMENT_DELETED_OK                  = 74;
    const POSTFINANCE_PAYMENT_DELETED_PROCESSED_MERCHANT  = 75;
    
    const POSTFINANCE_REFUNDED                            = 8;
    const POSTFINANCE_REFUND_WAITING                      = 81;
    const POSTFINANCE_REFUND_UNCERTAIN_STATUS             = 82;
    const POSTFINANCE_REFUND_REFUSED                      = 83;
    const POSTFINANCE_REFUND_DECLINED_ACQUIRER            = 84;
    const POSTFINANCE_REFUND_PROCESSED_MERCHANT           = 85;
    
    const POSTFINANCE_PAYMENT_REQUESTED                   = 9;
    const POSTFINANCE_PAYMENT_PROCESSING                  = 91;
    const POSTFINANCE_PAYMENT_UNCERTAIN                   = 92;
    const POSTFINANCE_PAYMENT_REFUSED                     = 93;
    const POSTFINANCE_PAYMENT_DECLINED_ACQUIRER           = 94;
    const POSTFINANCE_PAYMENT_PROCESSED_MERCHANT          = 95;
    const POSTFINANCE_PAYMENT_IN_PROGRESS                 = 99;

    /**
     * Layout of the payment method 
     */
    const PMLIST_HORIZONTAL_LEFT            = 0;
    const PMLIST_HORIZONTAL                 = 1;
    const PMLIST_VERTICAL                   = 2;

    /** 
     * payment action constant
     */
    const POSTFINANCE_AUTHORIZE_ACTION = 'RES';
    const POSTFINANCE_AUTHORIZE_CAPTURE_ACTION = 'SAL';
    const POSTFINANCE_CAPTURE_FULL = 'SAS';
    const POSTFINANCE_CAPTURE_PARTIAL = 'SAL';
    const POSTFINANCE_CAPTURE_DIRECTDEBIT_NL = 'VEN';
    const POSTFINANCE_DELETE_AUTHORIZE = 'DEL';
    const POSTFINANCE_DELETE_AUTHORIZE_AND_CLOSE = 'DES';
    const POSTFINANCE_REFUND_FULL = 'RFS';
    const POSTFINANCE_REFUND_PARTIAL = 'RFD';
    
    /**
     * 3D-Secure
     */
    const POSTFINANCE_DIRECTLINK_WIN3DS = 'MAINW';
    
    /** 
     * Module Transaction Type Codes 
     */
    const POSTFINANCE_CAPTURE_TRANSACTION_TYPE = 'capture';
    const POSTFINANCE_VOID_TRANSACTION_TYPE = 'void';
    const POSTFINANCE_REFUND_TRANSACTION_TYPE = 'refund';
    const POSTFINANCE_DELETE_TRANSACTION_TYPE = 'delete';

    /**
     * Return Config
     *
     * @return PostFinance_Payment_Model_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('postfinance/config');
    }

    /**
     * Redirect url to PostFinance submit form
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
          return $this->getConfig()->getPostFinanceUrl('placeform', true);
    }

    /**
     * Return payment_action value from config area
     *
     * @return string
     */
    public function getPaymentAction()
    {
        return $this->getConfig()->getConfigData('payment_action');
    }

    /**
     * @param $order Mage_Sales_Model_Order
     * @param null $requestParams
     * @return array
     */
    public function getMethodDependendFormFields($order, $requestParams = null)
    {
        $billingAddress = $order->getBillingAddress();

        $formFields = array();
        $formFields['CN']           = $billingAddress->getFirstname().' '.$billingAddress->getLastname();
        $formFields['OWNERZIP']     = $billingAddress->getPostcode();
        $formFields['OWNERCTY']     = $billingAddress->getCountry();
        $formFields['OWNERTOWN']    = $billingAddress->getCity();
        $formFields['COM']          = $this->_getOrderDescription($order);
        $formFields['OWNERTELNO']   = $billingAddress->getTelephone();        
        $formFields['OWNERADDRESS'] =  str_replace("\n", ' ',$billingAddress->getStreet(-1));
//        $formFields['ORIG']         = Mage::helper("postfinance")->getModuleVersionString();
        $formFields['BRAND']        = $this->getCardBrand($order->getPayment());

        return $formFields;
    }

    /**
     * Prepare params array to send it to gateway page via POST
     *
     * @param $order Mage_Sales_Model_Order
     * @param array
     * @return array
     */
    public function getFormFields($order, $requestParams)
    {
        if (empty($order)) {
            if (!($order = $this->getOrder())) {
                return array();
            }
        }
        $billingAddress = $order->getBillingAddress();
        $paymentCode = $order->getPayment()->getMethodInstance()->getPostFinanceCode();

        $formFields = array();
        $formFields['PSPID']    = $this->getConfig()->getPSPID();
        $formFields['AMOUNT']   = round($order->getBaseGrandTotal()*100);
        $formFields['CURRENCY'] = Mage::app()->getStore()->getCurrentCurrencyCode();
        $formFields['ORDERID']  = $this->getConfig()->getConfigData('devprefix').$order->getQuoteId();
        $formFields['LANGUAGE'] = Mage::app()->getLocale()->getLocaleCode();
        $formFields['PM']       = $this->getPaymentCode();
        $formFields['EMAIL']    = $order->getCustomerEmail();

        $methodDependendFields = $this->getMethodDependendFormFields($order, $requestParams);
        if (is_array($methodDependendFields)) {
            $formFields = array_merge($formFields, $methodDependendFields);
        }

        $paymentAction = $this->_getPaymentOperation();
        if ($paymentAction ) {
            $formFields['OPERATION'] = $paymentAction;
        }


        if ($this->getConfig()->getConfigData('template')=='postfinance') {
            $formFields['TP']= '';
            $formFields['PMLISTTYPE'] = $this->getConfig()->getConfigData('pmlist');
        } else {
            $formFields['TP']= $this->getConfig()->getPostFinanceUrl('paypage');
        }
        $formFields['TITLE']            = $this->getConfig()->getConfigData('html_title');
        $formFields['BGCOLOR']          = $this->getConfig()->getConfigData('bgcolor');
        $formFields['TXTCOLOR']         = $this->getConfig()->getConfigData('txtcolor');
        $formFields['TBLBGCOLOR']       = $this->getConfig()->getConfigData('tblbgcolor');
        $formFields['TBLTXTCOLOR']      = $this->getConfig()->getConfigData('tbltxtcolor');
        $formFields['BUTTONBGCOLOR']    = $this->getConfig()->getConfigData('buttonbgcolor');
        $formFields['BUTTONTXTCOLOR']   = $this->getConfig()->getConfigData('buttontxtcolor');
        $formFields['FONTTYPE']         = $this->getConfig()->getConfigData('fonttype');
        $formFields['LOGO']             = $this->getConfig()->getConfigData('logo');        
        $formFields['HOMEURL']          = $this->getConfig()->hasHomeUrl() ? $this->getConfig()->getContinueUrl(array('redirect' => 'home')) : 'NONE';
        $formFields['CATALOGURL']       = $this->getConfig()->hasCatalogUrl() ? $this->getConfig()->getContinueUrl(array('redirect' => 'catalog')) : '';
        $formFields['ACCEPTURL']        = $this->getConfig()->getPostFinanceUrl('accept');
        $formFields['DECLINEURL']       = $this->getConfig()->getPostFinanceUrl('decline');
        $formFields['EXCEPTIONURL']     = $this->getConfig()->getPostFinanceUrl('exception');
        $formFields['CANCELURL']        = $this->getConfig()->getPostFinanceUrl('cancel');
        $formFields['BACKURL']          = $this->getConfig()->getPostFinanceUrl('cancel');

        /** @var $paymentHelper PostFinance_Payment_Helper_Payment */
        $paymentHelper = Mage::helper('postfinance/payment');
        $plainHash = $paymentHelper->getSHASign($formFields);
        $shaSign = $paymentHelper->shaCrypt($plainHash);

        /** @var $helper PostFinance_Payment_Helper_Data */
        $helper = Mage::helper('postfinance');
        $helper->log($helper->__("Register Order %s in PostFinance \n\nAll form fields: %s\nPostFinance String to hash: %s\nHash: %s",
            $order->getIncrementId(),
            var_export($formFields, true),
            $plainHash,
            $shaSign
        ));

        $formFields['SHASIGN']  = $shaSign;
        
        return $formFields;
    }

    /**
     * Get PostFinance Payment Action value
     *
     * @param string
     * @return string
     */
    protected function _getPaymentOperation()
    {
        $value = $this->getPaymentAction();
        if ($value==Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
            $value = self::POSTFINANCE_AUTHORIZE_ACTION;
        } elseif ($value==Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
            $value = self::POSTFINANCE_AUTHORIZE_CAPTURE_ACTION;
        }
        return $value;
    }

    /**
     * get formatted order description
     *
     * @param $order Mage_Sales_Model_Order
     * @return string
     */
    protected function _getOrderDescription($order)
    {
        $invoiceDesc = '';
        /** @var $helper Mage_Core_Helper_String */
        $helper = Mage::helper('core/string');
        //COM field is limited to 100 chars max
        while ((list(, $item) = each($order->getAllItems())) && $helper->strlen($invoiceDesc.$item->getName()) > 100) {
            /** @var $item Mage_Sales_Model_Order_Item */
            if (!$item->getParentItem()){
                $invoiceDesc .= ($invoiceDesc ? ', ' : '') . preg_replace("/[^a-zA-Z0-9äáéèíóöõúüûÄÁÉÍÓÖÕÚÜÛ_ ]/" , "" , $item->getName());
            }
        }
        return $invoiceDesc;
     }
    /**
     * Get Main PostFinance Helper
     *
     * @return PostFinance_Payment_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('postfinance');
    }

    /**
     * Determines if a capture will be processed
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if (true === Mage::registry('postfinance_auto_capture')):
           Mage::unregister('postfinance_auto_capture');
           return parent::capture($payment, $amount);
        endif;

        $orderID = $payment->getOrder()->getId();
        $arrInfo = Mage::helper('postfinance/order_capture')->prepareOperation($payment, $amount);
        
        if(Mage::helper('postfinance/directlink')->checkExistingTransact(self::POSTFINANCE_CAPTURE_TRANSACTION_TYPE,  $orderID)):
            $this->getHelper()->redirectNoticed($orderID, $this->getHelper()->__('You already sent a capture request. Please wait until the capture request is acknowledged.'));
        endif;
        if(Mage::helper('postfinance/directlink')->checkExistingTransact(self::POSTFINANCE_VOID_TRANSACTION_TYPE,  $orderID)):
            $this->getHelper()->redirectNoticed($orderID, $this->getHelper()->__('There is one void request waiting. Please wait until this request is acknowledged.'));
        endif;
        
        try {
            $requestParams  = array(
                'AMOUNT' => round($amount*100),
                'ORDERID' => $this->getConfig()->getConfigData('devprefix').$payment->getOrder()->getQuoteId(),
                'OPERATION' => $arrInfo['operation']
            );
            $response = Mage::getSingleton('postfinance/api_directlink')->performRequest($requestParams, Mage::getModel('postfinance/config')->getDirectLinkGatewayPath());
            Mage::helper('postfinance/payment')->savePostFinanceStatusToPayment($payment, $response);
            
            if ($response['STATUS'] == self::POSTFINANCE_PAYMENT_PROCESSING ||
                $response['STATUS'] == self::POSTFINANCE_PAYMENT_UNCERTAIN ||
                $response['STATUS'] == self::POSTFINANCE_PAYMENT_IN_PROGRESS
                ):
                Mage::helper('postfinance/directlink')->directLinkTransact(
                    Mage::getSingleton("sales/order")->loadByIncrementId($payment->getOrder()->getIncrementId()), 
                    $response['PAYID'], 
                    $response['PAYIDSUB'], 
                    $arrInfo, 
                    self::POSTFINANCE_CAPTURE_TRANSACTION_TYPE,
                    $this->getHelper()->__('Start PostFinance %s capture request',$arrInfo['type']));
                /** @var $order Mage_Sales_Model_Order */
                $order = Mage::getModel('sales/order')->load($orderID); //Reload order to avoid wrong status
                $order->addStatusHistoryComment(
                    Mage::helper('postfinance')->__(
                        'Invoice will be created automatically as soon as PostFinance sends an acknowledgement. PostFinance status: %s.',
                        $this->getHelper()->getStatusText($response['STATUS'])
                    )
                );
                $order->save();
                $this->getHelper()->redirectNoticed(
                    $orderID,
                    $this->getHelper()->__(
                        'Invoice will be created automatically as soon as PostFinance sends an acknowledgement. PostFinance status: %s.',
                        $this->getHelper()->getStatusText($response['STATUS'])
                    )
                );
            elseif ($response['STATUS'] == self::POSTFINANCE_PAYMENT_PROCESSED_MERCHANT || $response['STATUS'] == self::POSTFINANCE_PAYMENT_REQUESTED):
                 return parent::capture($payment, $amount);
            else:
                 Mage::throwException(
                     $this->getHelper()->__(
                         'The Invoice was not created. PostFinance status: %s.',
                         $this->getHelper()->getStatusText($response['STATUS'])
                     )
                 );
            endif;
        }
        catch (Exception $e){
            $this->getHelper()->log("Exception in capture request:".$e->getMessage());
            throw new Mage_Core_Exception($e->getMessage());
        }
    }

    /**
     * Refund
     * 
     * @param Varien_Object $payment 
     * @param float $amount 
     * @return 
     */
     public function refund(Varien_Object $payment, $amount)
     {
        //If the refund will be created by PostFinance, Refund Create Method to nothing
        if (true === Mage::registry('postfinance_auto_creditmemo')) {
           Mage::unregister('postfinance_auto_creditmemo');
           return parent::refund($payment, $amount);
        }

        /** @var $refundHelper PostFinance_Payment_Helper_Order_Refund */
        $refundHelper = Mage::helper('postfinance/order_refund');
        $refundHelper->setPayment($payment)
                     ->setAmount($amount);
        
        $operation = $refundHelper->getRefundOperation($payment, $amount);
        $requestParams  = array(
            'AMOUNT' => round($amount*100),
            'ORDERID' => $this->getConfig()->getConfigData('devprefix').$payment->getOrder()->getQuoteId(),
            'OPERATION' => $operation
        );
        
        try {
            $url = Mage::getModel('postfinance/config')->getDirectLinkGatewayPath();
            $response = Mage::getModel('postfinance/api_directlink')->performRequest($requestParams, $url);
            Mage::helper('postfinance/payment')->savePostFinanceStatusToPayment($payment, $response);
            
            if (($response['STATUS'] == self::POSTFINANCE_REFUND_WAITING)
                || ($response['STATUS'] == self::POSTFINANCE_REFUND_UNCERTAIN_STATUS)):
                $refundHelper->createRefundTransaction($response);
            elseif (($response['STATUS'] == self::POSTFINANCE_REFUNDED)
                    || ($response['STATUS'] == self::POSTFINANCE_REFUND_PROCESSED_MERCHANT)):
                //do refund directly if response is ok already
                $refundHelper->createRefundTransaction($response, 1);
                return parent::refund($payment, $amount);
            else:
                Mage::throwException($this->getHelper()->__('The CreditMemo was not created. PostFinance status: %s.',$response['status']));
            endif;
            
            Mage::getSingleton('core/session')->addNotice($this->getHelper()->__('The Creditmemo will be created automatically as soon as PostFinance sends an acknowledgement.'));
            $this->getHelper()->redirect(
                Mage::getUrl('*/sales_order/view', array('order_id' => $payment->getOrder()->getId()))
            );
        }
        catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }
    
    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund()
    {
        try
        {
            $order = Mage::getModel('sales/order')->load(Mage::app()->getRequest()->getParam('order_id'));
            if (false === Mage::helper('postfinance/directlink')->hasPaymentTransactions($order,self::POSTFINANCE_REFUND_TRANSACTION_TYPE)):
                return $this->_canRefund;
            else:
                //Add the notice if no exception was thrown, because in this case there is one creditmemo in the transaction queue
                Mage::getSingleton('core/session')->addNotice(
                    $this->getHelper()->__('There is already one creditmemo in the queue. The Creditmemo will be created automatically as soon as PostFinance sends an acknowledgement.')
                );
                $this->getHelper()->redirect(
                    Mage::getUrl('*/sales_order/view', array('order_id' => $order->getId()))
                );
            endif;
        }
        catch (Exception $e)
        {
              Mage::getSingleton('core/session')->addError($e->getMessage());
              return $this->_canRefund;
        }
    }
    
    public function cancel(Varien_Object $payment)
    {
        if (true === Mage::registry('postfinance_auto_void')):
           Mage::unregister('postfinance_auto_void');
           return parent::cancel($payment);
        endif;
        throw new Mage_Core_Exception($this->getHelper()->__('Please use void to cancel the operation.'));
    }

    public function void(Varien_Object $payment)
    {
         if (true === Mage::registry('postfinance_auto_void')):
           Mage::unregister('postfinance_auto_void');
           return parent::void($payment);
        endif;

        $params = Mage::app()->getRequest()->getParams();
        $order = Mage::getModel("sales/order")->load($params['order_id']);
        $orderID = $payment->getOrder()->getId();
        
        $alreadyCaptured = Mage::helper('postfinance/order_void')->getCapturedAmount($order);
        $voidAmount = $order->getGrandTotal() - $alreadyCaptured;

        $requestParams  = array(
            'AMOUNT' => round($voidAmount * 100),
            'ORDERID' => $this->getConfig()->getConfigData('devprefix').$order->getQuoteId(),
            'OPERATION' => self::POSTFINANCE_DELETE_AUTHORIZE
        );

        if (Mage::helper('postfinance/directlink')->checkExistingTransact(self::POSTFINANCE_VOID_TRANSACTION_TYPE,  $orderID)){
            $this->getHelper()->redirectNoticed($orderID, $this->getHelper()->__('You already sent a void request. Please wait until the void request will be acknowledged.'));
        }
        if (Mage::helper('postfinance/directlink')->checkExistingTransact(self::POSTFINANCE_CAPTURE_TRANSACTION_TYPE,  $orderID)){
            $this->getHelper()->redirectNoticed($orderID, $this->getHelper()->__('There is one capture request waiting. Please wait until this request is acknowledged.'));
        }

        try {
            $url = Mage::getModel('postfinance/config')->getDirectLinkGatewayPath();
            $response = Mage::getSingleton('postfinance/api_directlink')->performRequest($requestParams, $url);
            Mage::helper('postfinance/payment')->savePostFinanceStatusToPayment($payment, $response);

            if ($response['STATUS'] == self::POSTFINANCE_VOID_WAITING || $response['STATUS'] == self::POSTFINANCE_VOID_UNCERTAIN):
                Mage::helper('postfinance/directlink')->directLinkTransact(
                   Mage::getSingleton("sales/order")->loadByIncrementId($payment->getOrder()->getIncrementId()), // reload order to avoid canceling order before confirmation from PostFinance
                   $response['PAYID'],
                   $response['PAYIDSUB'],
                   array(
                       'amount' => $voidAmount,
                       'void_request' => Mage::app()->getRequest()->getParams(),
                       'response'     => $response,
                   ),
                   self::POSTFINANCE_VOID_TRANSACTION_TYPE,
                   Mage::helper('postfinance')->__('Start PostFinance void request. PostFinance status: %s.', $this->getHelper()->getStatusText($response['STATUS'])));
                $this->getHelper()->redirectNoticed($orderID, $this->getHelper()->__('The void request is sent. Please wait until the void request will be accepted.'));
            elseif ($response['STATUS'] == self::POSTFINANCE_VOIDED || $response['STATUS'] == self::POSTFINANCE_VOIDED_ACCEPTED):
                Mage::helper('postfinance/directlink')->directLinkTransact(
                   Mage::getSingleton("sales/order")->loadByIncrementId($payment->getOrder()->getIncrementId()), // reload order to avoid canceling order before confirmation from PostFinance
                   $response['PAYID'],
                   $response['PAYIDSUB'],
                   array(),
                   self::POSTFINANCE_VOID_TRANSACTION_TYPE,
                   $this->getHelper()->__('Void order succeed. PostFinance status: %s.',$response['STATUS']),
                   1);
                return parent::void($payment);
            else: 
                Mage::throwException($this->getHelper()->__('Void order failed. PostFinance status: %s.',$response['STATUS']));
            endif;
        }
        catch (Exception $e){
            Mage::helper('postfinance')->log("Exception in void request:".$e->getMessage());
            throw new Mage_Core_Exception($e->getMessage());
        }
    }

    /** PostFinance payment code */
    protected function getPaymentCode() {
        return ucfirst(substr($this->_code, strpos($this->_code, '_')+1));
    }

    public function getCardBrand($payment=null) {
        return $this->getPaymentCode($payment);
    }

    /**
     * get question for fields with disputable value
     * users are asked to correct the values before redirect to PostFinance
     * 
     * @param mixed $quote 
     * @return string
     */
    public function getQuestion($quote, $requestParams) {}

    /**
     * get an array of fields with disputable value
     * users are asked to correct the values before redirect to PostFinance
     * 
     * @param Mage_Sales_Model_Order $quote 
     * @return array
     */
    public function getQuestionedFormFields($quote, $requestParams)
    {
        return array();
    }
}
