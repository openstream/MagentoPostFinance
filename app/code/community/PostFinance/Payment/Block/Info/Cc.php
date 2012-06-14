<?php

class PostFinance_Payment_Block_Info_Cc extends PostFinance_Payment_Block_Info_Redirect
{
    /**
     * Init ops payment information block
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('postfinance/info/cc.phtml');
    }
}

