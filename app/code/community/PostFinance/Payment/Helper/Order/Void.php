<?php

class PostFinance_Payment_Helper_Order_Void extends Mage_Core_Helper_Abstract
{
    /**
     * Cancels the appropriate order and closes the transaction
     *
     *
     * @param <type> $params
     */
    public function acceptVoid($params)
    {
        Mage::register('postfinance_auto_void', true);
        $order = Mage::getModel("sales/order")->loadByAttribute('quote_id', $params['orderID']);
        $order->cancel();
        $order->save();
        Mage::helper("postfinance/directlink")->closePaymentTransaction(
            $order,
            $params,
            PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_VOID_TRANSACTION_TYPE
        );
        Mage::helper('postfinance')->log("order voided: ".$params['orderID']);
    }

    public function getCapturedAmount($order)
    {
        $sumAmount = 0;
        $transactionCollection = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addAttributeToFilter('txn_type', PostFinance_Payment_Model_Payment_Abstract::POSTFINANCE_CAPTURE_TRANSACTION_TYPE)
            ->addAttributeToFilter('order_id', $order->getId())
            ->addAttributeToFilter('is_closed', 1);

        foreach($transactionCollection as $transaction){
            $arrInfo = null;
            $arrInfoRough = $transaction->getAdditionalInformation();
            if(isset($arrInfoRough['arrInfo'])){
                $arrInfo = unserialize($arrInfoRough['arrInfo']);
            }

            if(isset($arrInfo['amount'])){
                $sumAmount += $arrInfo['amount'];
            }
        }
        return $sumAmount;
    }
}