<?php

class PostFinance_Payment_Block_Paypage extends Mage_Core_Block_Template
{
    /**
     * Init pay page block
     *
     * @return PostFinance_Payment_Block_Paypage
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('postfinance/paypage.phtml');
        return $this;
    }
}
