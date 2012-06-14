<?php

class PostFinance_Payment_ApiController extends PostFinance_Payment_Controller_Abstract
{
    /**
     * Order instance
     */
    protected $_order;

    /*
     * Predispatch to check the validation of the request from PostFinance
     */
    public function preDispatch()
    {
        if (!$this->_validatePostFinanceData()) {
            throw new Exception ("Hash not valid");
        }
    }

    /**
     * Action to control postback data from PostFinance
     *
     */
    public function postBackAction()
    {
        $params = $this->getRequest()->getParams();
        try {
            $this->getPaymentHelper()->applyStateForOrder(
                $this->_getOrder(),
                $params
            );
        } catch (Exception $e) {
            Mage::log("Fatal Exception in postBackAction:" .$e->getMessage());
            $this->_redirect('checkout/cart');
            return;
        }
    }
    
    /**
     * Action to control postback data from PostFinance
     *
     */
    public function directLinkPostBackAction()
    {
        $params = $this->getRequest()->getParams();
        try {
            $this->getDirectlinkHelper()->processFeedback(
                $this->_getOrder(),
                $params
            );
        } catch (Exception $e) {
            $msq = "Fatal Exception in directLinkPostBackAction:" .$e->getMessage();
            Mage::log($msq);
            die($msq);
        }
    }
}
