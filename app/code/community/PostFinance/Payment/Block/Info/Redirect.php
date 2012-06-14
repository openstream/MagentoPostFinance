<?php

class PostFinance_Payment_Block_Info_Redirect extends Mage_Payment_Block_Info
{
    /**
     * Init ops payment information block
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('postfinance/info/redirect.phtml');
    }
}
