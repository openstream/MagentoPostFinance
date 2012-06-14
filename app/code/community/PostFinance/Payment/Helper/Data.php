<?php

class Postfinance_Payment_Helper_Data extends Mage_Core_Helper_Abstract
{
    const LOG_FILE_NAME = 'postfinance.log';

    /**
     * Returns config model
     * 
     * @return PostFinance_Payment_Model_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('postfinance/config');
    }
    
    /**
     * Checks if logging is enabled and if yes, logs given message to logfile
     * 
     * @param string $message
     * @param int $level
     */
    public function log($message, $level = null)
    {
        if($this->getConfig()->shouldLogRequests()){
            Mage::log($message, $level, self::LOG_FILE_NAME);
        }
    }
    
    public function redirect($url)
    {
        Mage::app()->getResponse()->setRedirect($url);
        Mage::app()->getResponse()->sendResponse();
        exit();
    }

    /**
     * Redirects to the given order and prints some notice output
     *
     * @param int $orderId
     * @param string $message
     * @return void
    */
    public function redirectNoticed($orderId, $message)
    {
        Mage::getSingleton('core/session')->addNotice($message);
        $this->redirect(
            Mage::getUrl('*/sales_order/view', array('order_id' => $orderId))
        );
    }

    public function getStatusText($statusCode)
    {
        $translationOrigin = "STATUS_".$statusCode;
        $translationResult = $this->__($translationOrigin);
        if ($translationOrigin != $translationResult):
            return $translationResult. " ($statusCode)";
        else:
            return $statusCode;
        endif;
    }
}
