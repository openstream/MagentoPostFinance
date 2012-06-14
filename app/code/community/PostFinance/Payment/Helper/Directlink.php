<?php

class PostFinance_Payment_Helper_DirectLink extends Mage_Core_Helper_Abstract
{
    /**
     * Creates Transactions for directlink activities
     *
     * @param Mage_Sales_Model_Order $order
     * @param int $transactionID - persistent transaction id
     * @param int $subPayID - identifier for each transaction
     * @param array $arrInformation - add dynamic data
     * @param string $typename - name for the transaction exp.: refund
     * @param string $comment - order comment
     * 
     * @return PostFinance_Payment_Helper_DirectLink $this
     */
    public function directLinkTransact($order,$transactionID, $subPayID,
        $arrInformation = array(), $typename, $comment, $closed = 0)
    {
        $payment = $order->getPayment();
        $payment->setTransactionId($transactionID."/".$subPayID);
        $transaction = $payment->addTransaction($typename, null, false, $comment);
        $transaction->setParentTxnId($transactionID);
        $transaction->setIsClosed($closed);
        $transaction->setAdditionalInformation("arrInfo", serialize($arrInformation));
        $transaction->save();
        $order->save();
        return $this;
    }

    /**
     * Checks if there is an active transaction for a special order for special
     * type
     *
     * @param string $type - refund, capture etc.
     * @param int $orderID
     * @return bol success
     */
    public function checkExistingTransact($type, $orderID)
    {
        $transaction = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('order_id', $orderID)
            ->addAttributeToFilter('txn_type', $type)
            ->addAttributeToFilter('is_closed', 0)
            ->getLastItem();

        return ($transaction->getTxnId()) ? true : false;
    }

    /**
     * get transaction type for given postfinance status
     *
     * @param string $status
     *
     * @return string
     */
    public function getTypeForStatus($status)
    {
        switch ($status) {
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUNDED :
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_WAITING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_UNCERTAIN_STATUS :
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_REFUSED :
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_DECLINED_ACQUIRER :
                return PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_TRANSACTION_TYPE;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_REQUESTED :
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_PROCESSED_MERCHANT :
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_PROCESSING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_UNCERTAIN:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_IN_PROGRESS:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_REFUSED:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_DECLINED_ACQUIRER:
                return PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_CAPTURE_TRANSACTION_TYPE;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOIDED: //Void finished
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOIDED_ACCEPTED:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_WAITING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_UNCERTAIN:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_REFUSED:
                return PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_TRANSACTION_TYPE;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_DELETED:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_DELETED_WAITING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_DELETED_UNCERTAIN:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_DELETED_REFUSED:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_DELETED_OK:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_DELETED_PROCESSED_MERCHANT:
                return PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_DELETE_TRANSACTION_TYPE;
        }
    }

    /**
     * Process Direct Link Feedback to do: Capture, De-Capture and Refund
     * 
     * @param Mage_Sales_Model_Order $order  Order
     * @param array                  $params Request params
     *
     * @return void
     */
    public function processFeedback($order, $params)
    {
        Mage::helper('postfinance/payment')->savePostFinanceStatusToPayment($order->getPayment(), $params);
        try {
            $transaction = $this->getPaymentTransaction($order, null, $this->getTypeForStatus($params['STATUS']));
        } catch (Mage_Core_Exception $e) {
            $transaction = null;
        }
        
        if (false == $this->isValidPostFinanceRequest($transaction, $order, $params)) {
            $order->addStatusHistoryComment(
                Mage::helper('postfinance')->__(
                    'Could not perform actions for PostFinance status: %s.',
                    Mage::helper('postfinance')->getStatusText($params['STATUS'])
                )
            )->save();
            throw new Mage_Core_Exception('invalid PostFinance request');
        }
        
        switch ($params['STATUS']) {
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_INVALID :
                break;

            /*
             * Refund Actions
             */
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUNDED :
                Mage::helper('postfinance/order_refund')->createRefund($order, $params);
                break;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_WAITING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_UNCERTAIN_STATUS :
                $order->addStatusHistoryComment(
                    Mage::helper('postfinance')->__(
                        'Refund is waiting or uncertain. PostFinance status: %s.',
                        Mage::helper('postfinance')->getStatusText($params['STATUS'])
                    )
                ); 
                $order->save();
                break;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_REFUSED :
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_DECLINED_ACQUIRER :
                $this->closePaymentTransaction(
                    $order, 
                    $params, 
                    PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_TRANSACTION_TYPE,
                    Mage::helper('postfinance')->__(
                        'Refund was refused. Automatic creation failed. PostFinance status: %s.',
                        Mage::helper('postfinance')->getStatusText($params['STATUS'])
                    )
                );
                break;

            /*
             * Capture Actions
             */
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_REQUESTED :
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_PROCESSED_MERCHANT :
                Mage::helper("postfinance/order_capture")->acceptCapture($params);
                break;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_PROCESSING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_UNCERTAIN:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_IN_PROGRESS:
                $order->addStatusHistoryComment(
                    Mage::helper('postfinance')->__(
                        'Capture is waiting or uncertain. PostFinance status: %s.',
                        Mage::helper('postfinance')->getStatusText($params['STATUS'])
                    )
                );
                $order->save();
                break;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_REFUSED:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_PAYMENT_DECLINED_ACQUIRER:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTH_REFUSED :
                $this->closePaymentTransaction(
                    $order,
                    $params,
                    PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_CAPTURE_TRANSACTION_TYPE,
                    Mage::helper('postfinance')->__(
                        'Capture was refused. Automatic creation failed. PostFinance status: %s.',
                        $params['STATUS']
                    )
                );
                break;

            /*
             * Void Actions
             */
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOIDED: //Void finished
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOIDED_ACCEPTED:
                Mage::helper("postfinance/order_void")->acceptVoid($params);
                break;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_WAITING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_UNCERTAIN:
                $order->addStatusHistoryComment(
                    Mage::helper('postfinance')->__(
                        'Void is waiting or uncertain. PostFinance status: %s.',
                        Mage::helper('postfinance')->getStatusText($params['STATUS'])
                    )
                );
                $order->save();
                break;
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_REFUSED:
                $this->closePaymentTransaction(
                    $order,
                    $params,
                    PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_TRANSACTION_TYPE,
                    Mage::helper('postfinance')->__(
                        'Void was refused. Automatic creation failed. PostFinance status: %s.',
                        Mage::helper('postfinance')->getStatusText($params['STATUS'])
                    )
                );
                break;
                
            /*
             * Authorize Actions
             */
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_WAITING:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_UNKNOWN:
            case PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_AUTHORIZED_TO_GET_MANUALLY:
                $order->addStatusHistoryComment(Mage::helper('postfinance')->__('Authorization status changed. Current PostFinance status is: %s.', Mage::helper('postfinance')->getStatusText($params['STATUS'])));
                $order->save();
                break;
                
            default:
                $order->addStatusHistoryComment(
                    Mage::helper('postfinance')->__('Unknown PostFinance status: %s.', Mage::helper('postfinance')->getStatusText($params['STATUS']))
                );
                $order->save();
                Mage::helper("postfinance")->log("Unknown status code:".$params['STATUS']);
                break;
        }
    }

    /**
     * Get the payment transaction by PAYID and Operation
     * 
     * @param Mage_Sales_Model_Order $order 
     * @param int                    $payid
     * @param string                 $authorization
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    public function getPaymentTransaction($order, $payid, $operation)
    {
        $helper = Mage::helper('postfinance');
        $transactionCollection = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('txn_type', $operation)
            ->addAttributeToFilter('is_closed', 0)
            ->addAttributeToFilter('order_id', $order->getId());
        if ($payid != '') {
            $transactionCollection->addAttributeToFilter('parent_txn_id', $payid);
        }

        if ($transactionCollection->count()>1 || $transactionCollection->count() == 0) {
            $errorMsq = $helper->__(
                'Warning, transaction count is %s instead of 1 for the Payid "%s", order "%s" and Operation "%s".',
                $transactionCollection->count(),
                $payid,
                $order->getId(),
                $operation
            );
            $helper->log($errorMsq);
            throw new Mage_Core_Exception($errorMsq);
        }

        if ($transactionCollection->count() == 1) {
            $transaction = $transactionCollection->getLastItem();
            $transaction->setOrderPaymentObject($order->getPayment());
            return $transaction;
        }
    }


    /**
     * Check if there are payment transactions for an order and an operation
     * 
     * @param Mage_Sales_Model_Order $order 
     * @param string $authorization
     *
     * @return boolean
     */
    public function hasPaymentTransactions($order, $operation)
    {
        $helper = Mage::helper('postfinance');
        $transactionCollection = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('txn_type', $operation)
            ->addAttributeToFilter('is_closed', 0)
            ->addAttributeToFilter('order_id', $order->getId());

        return (0 < $transactionCollection->count());
    }

    /**
     * determine if the current postfinance request is valid
     * 
     * @param array                  $transactions     Iteratable of Mage_Sales_Model_Order_Payment_Transaction 
     * @param Mage_Sales_Model_Order $order
     * @param array                  $postfinanceRequestParams
     *
     * @return boolean
     */
    public function isValidPostFinanceRequest($openTransaction, Mage_Sales_Model_Order $order, $postfinanceRequestParams)
    {
        if ($this->getTypeForStatus($postfinanceRequestParams['STATUS']) == PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_DELETE_TRANSACTION_TYPE) {
            return false;
        }

        $requestedAmount = null;
        if (array_key_exists('amount', $postfinanceRequestParams)) {
            $requestedAmount = $postfinanceRequestParams['amount'];
        }

        /* find expected amount */
        $expectedAmount = null;
        if (false === is_null($openTransaction)) {
            $transactionInfo = unserialize($openTransaction->getAdditionalInformation('arrInfo'));
            if (array_key_exists('amount', $transactionInfo)) {
                if (
                    is_null($expectedAmount)
                    || (float) $transactionInfo['amount'] == (float) $requestedAmount
                ) {
                    $expectedAmount = $transactionInfo['amount'];
                }
            }
        }

        if ($this->getTypeForStatus($postfinanceRequestParams['STATUS']) == PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_TRANSACTION_TYPE
            || $this->getTypeForStatus($postfinanceRequestParams['STATUS']) == PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_TRANSACTION_TYPE
        ) {
            if (is_null($requestedAmount)
                || 0 == count($openTransaction)
                || (float) $requestedAmount != (float) $expectedAmount
            ) {
                return false;
            }
        }

        if ($this->getTypeForStatus($postfinanceRequestParams['STATUS']) == PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_CAPTURE_TRANSACTION_TYPE) {
            if (is_null($requestedAmount)) {
                Mage::helper('postfinance')->log('Please configure PostFinance to submit amount');
                return false;
            }
            if ($order->getGrandTotal() != $requestedAmount) {
                if (is_null($openTransaction)
                    || (float) $expectedAmount != (float) $requestedAmount
                ) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Close a payment transaction
     * 
     * @param Mage_Sales_Model_Order $order 
     * @param array $params
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    public function closePaymentTransaction($order, $params, $type, $comment = "", $isCustomerNotified = false)
    {
        $transaction = Mage::helper('postfinance/directlink')->getPaymentTransaction(
            $order,
            $params['PAYID'], 
            $type
        );

        if (1 !== $transaction->getIsClosed()) {
            $transaction->setIsClosed(1);
            $transaction->save();
        }

        $trandactionID = $transaction->getTxnId();
        if ($comment) {
            $comment .= ' Transaction ID: '.'"'.$trandactionID.'"';
            $order
               ->addStatusHistoryComment($comment)
               ->setIsCustomerNotified($isCustomerNotified);
        }
        $order->save();
    }
}
