<?php

class PostFinance_Payment_Helper_Order_Refund extends Mage_Core_Helper_Abstract
{
    protected $payment;
    protected $amount;
    
    /**
     * @param Varien_Object $payment 
     */
    public function setPayment(Varien_Object $payment)
    {
        $this->payment = $payment;
        return $this;
    }
    
    /**
     * @param float $amount 
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }
 
    /**
     * Return the refund operation type (RFS or RFD)
     * 
     * @param Varien_Object $payment 
     * @param float $amount 
     * @return 
     */
    public function getRefundOperation()
    {
        return PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_PARTIAL;
    }
    
    /**
     * Create a new payment transaction for the refund
     * 
     * @param array $response 
     * @return 
     */
    public function createRefundTransaction($response, $closed = 0)
    {
        $transactionParams = array(
            'creditmemo_request' => Mage::app()->getRequest()->getParams(),
            'response'     => $response,
            'amount'             => $this->amount
        );
     
        Mage::helper('postfinance/directlink')->directLinkTransact(
            Mage::getModel('sales/order')->load($this->payment->getOrder()->getId()),
            $response['PAYID'],
            $response['PAYIDSUB'],
            $transactionParams, 
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_TRANSACTION_TYPE,
            Mage::helper('postfinance')->__('Start PostFinance refund request'),
            $closed
        );
        
        $order = Mage::getModel('sales/order')->load($this->payment->getOrder()->getId());
        $order->addStatusHistoryComment(
            Mage::helper('postfinance')->__(
               'Creditmemo will be created automatically as soon as PostFinance sends an acknowledgement. PostFinance Status: %s.',
               Mage::helper('postfinance')->getStatusText($response['STATUS'])
            )
        );
        $order->save();
    }
    
    /**
     * Create a new refund
     * 
     * @param Mage_Sales_Model_Order $order 
     * @param array $params
     * @return 
     */
    public function createRefund($order, $params)
    {
        $refundTransaction = Mage::helper('postfinance/directlink')->getPaymentTransaction(
            $order,
            $params['PAYID'], 
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_TRANSACTION_TYPE
        );
        $transactionParams = $refundTransaction->getAdditionalInformation();
        $transactionParams = unserialize($transactionParams['arrInfo']);
        
        try {
            //Start to create the creditmemo
            Mage::register('postfinance_auto_creditmemo', true);
            $service = Mage::getModel('sales/service_order', $order);
            $invoice = Mage::getModel('sales/order_invoice')
                    ->load($transactionParams['creditmemo_request']['invoice_id'])
                    ->setOrder($order);
            $data = $this->prepareCreditMemoData($transactionParams);
            $creditmemo = $service->prepareInvoiceCreditmemo($invoice, $data);
            
            /**
              * Process back to stock flags
            */
            $backToStock = $data['backToStock'];
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                    $orderItem = $creditmemoItem->getOrderItem();
                    $parentId = $orderItem->getParentItemId();
                    if (isset($backToStock[$orderItem->getId()])) {
                        $creditmemoItem->setBackToStock(true);
                    } elseif ($orderItem->getParentItem() && isset($backToStock[$parentId]) && $backToStock[$parentId]) {
                        $creditmemoItem->setBackToStock(true);
                    } elseif (empty($savedData)) {
                        $creditmemoItem->setBackToStock(Mage::helper('cataloginventory')->isAutoReturnEnabled());
                    } else {
                        $creditmemoItem->setBackToStock(false);
                    }
            }
            
            //Send E-Mail and Comment
            $comment = '';
            $sendEmail = false;
            $sendEMailWithComment = false;
            if (isset($data['send_email']) && $data['send_email'] == 1) $sendEmail = true;
            if (isset($data['comment_customer_notify'])) $sendEMailWithComment = true;
            
            if (!empty($data['comment_text'])):
                    $creditmemo->addComment($data['comment_text'], $sendEMailWithComment);
                    if ($sendEMailWithComment):
                        $comment = $data['comment_text'];
                    endif;
            endif;

            
            $creditmemo->setRefundRequested(true);
            $creditmemo->setOfflineRequested(false);
            $creditmemo->register();
            if ($sendEmail):
                    $creditmemo->setEmailSent(true);
            endif;
            $creditmemo->getOrder()->setCustomerNoteNotify($sendEMailWithComment);
            
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($creditmemo)
                ->addObject($creditmemo->getOrder());
            if ($creditmemo->getInvoice()):
                $transactionSave->addObject($creditmemo->getInvoice());
            endif;
            $creditmemo->sendEmail($sendEmail, $comment);
            $transactionSave->save();
            //End of create creditmemo
            
            //close refund payment transaction
            Mage::helper('postfinance/directlink')->closePaymentTransaction(
               $order, 
               $params, 
               PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_REFUND_TRANSACTION_TYPE,
               Mage::helper('postfinance')->__(
                   'Creditmemo "%s" was created automatically. PostFinance Status: %s.',
                   $creditmemo->getIncrementId(),
                   Mage::helper('postfinance')->getStatusText($params['STATUS'])
               ),
               $sendEmail
            );
        }
        catch (Exception $e) {
            Mage::throwException('Error in Creditmemo creation process: '.$e->getMessage());
        }
     
    }
    
    /**
     * Get requested items qtys
     */
    protected function prepareCreditMemoData($transactionParams)
    {
        $data = $transactionParams['creditmemo_request']['creditmemo'];
        $qtys = array();
        $backToStock = array();
        foreach ($data['items'] as $orderItemId =>$itemData):
           if (isset($itemData['qty'])):
               $qtys[$orderItemId] = $itemData['qty'];
           else:
               if (isset($itemData['back_to_stock'])):
                   $backToStock[$orderItemId] = true;
               endif;
           endif;
        endforeach;
        $data['qtys'] = $qtys;
        $data['backToStock'] = $backToStock;
        return $data;
    }
}
