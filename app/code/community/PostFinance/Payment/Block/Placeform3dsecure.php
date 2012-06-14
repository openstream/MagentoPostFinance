<?php

class PostFinance_Payment_Block_Placeform3dsecure extends PostFinance_Payment_Block_Placeform
{
    /**
     * Get Form data of 3D Secure
     *
     * @return string
     */
    public function getFormData()
    {
        return base64_decode($this->_getOrder()->getPayment()->getAdditionalInformation('HTML_ANSWER'));
    }
}
