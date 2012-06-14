<?php

class PostFinance_Payment_Model_Payment_Efinance extends PostFinance_Payment_Model_Payment_Abstract
{
    /** Check if can capture directly from the backend */
    protected $_canBackendDirectCapture = true;

    /** info source path */
    protected $_infoBlockType = 'postfinance/info_redirect';

    /** payment code */
    protected $_code = 'postfinance_efinance';

    /** payment code */
    protected function getPaymentCode() {
        return 'PostFinance E-Finance';
    }
}

