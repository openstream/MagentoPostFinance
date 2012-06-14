<?php

class PostFinance_Payment_Model_Api_DirectLink extends Mage_Core_Model_Abstract
{

    /**
     * Perform a CURL call and log request end response to logfile
     *
     * @param array $params
     * @return mixed
     */
     public function call($params, $url)
     {
         try {
             $http = new Varien_Http_Adapter_Curl();
             $config = array('timeout' => 30);
             $http->setConfig($config);
             $http->write(Zend_Http_Client::POST, $url, '1.1', array(), http_build_query($params));
             $response = $http->read();
             $response = substr($response, strpos($response, "<?xml"), strlen($response));
             return $response;
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException(
                Mage::helper('postfinance')->__('PostFinance server is temporarily not available, please try again later.')
            );
        }
     }

    /**
     * Performs a POST request to the Direct Link Gateway with the given
     * parameters and returns the result parameters as array
     *
     * @param array $params
     * @return array
     */
     public function performRequest($requestParams, $url)
     {
        /** @var $helper PostFinance_Payment_Helper_Data */
        $helper = Mage::helper('postfinance');
        $params = $this->getEncodedParametersWithHash(
            array_merge($requestParams,$this->buildAuthenticationParams()) //Merge Logic Operation Data with Authentication Data
        );

        $responseParams = $this->getParamArrFromXmlString(
            $this->call($params, $url)
        );

        $helper->log($helper->__("Direct Link Request/Response in Postfinance \n\nRequest: %s\nResponse: %s\nMagento-URL: %s\nAPI-URL: %s",
            serialize($params),
            serialize($responseParams),
            Mage::helper('core/url')->getCurrentUrl(),
            $url
        ));
        
        $this->checkResponse($responseParams);

        return $responseParams;

     }

     public function getEncodedParametersWithHash($params, $shaCode=null)
     {
        $params['SHASIGN'] = Mage::helper('postfinance/payment')->shaCrypt(iconv('iso-8859-1', 'utf-8', Mage::helper('postfinance/payment')->getSHASign($params, $shaCode)));

        return $params;
     }

    /**
     * Return Authentication Params for PostFinance Call
     *
     * @return array
     */
     protected function buildAuthenticationParams()
     {
         return array(
             'PSPID' => Mage::getModel('postfinance/config')->getPSPID(),
             'USERID' => Mage::getModel('postfinance/config')->getApiUserId(),
             'PSWD' => Mage::getModel('postfinance/config')->getApiPswd(),
         );
     }

     /**
     * Parses the XML-String to an array with the result data
     *
     * @param string xmlString
     * @return array
     */
     public function getParamArrFromXmlString($xmlString)
     {
         try {
             $xml = new SimpleXMLElement($xmlString);
             foreach($xml->attributes() as $key => $value) {
                 $arrAttr[$key] = (string)$value;
             }
             foreach($xml->children() as $child) {
                 $arrAttr[$child->getName()] = (string) $child;
             }
             return $arrAttr;
         } catch (Exception $e) {
             Mage::log('Could not convert string to xml in ' . __FILE__ . '::' . __METHOD__ . ': ' . $xmlString);
             Mage::logException($e);
         }
     }
     
     /**
     * Check if the Response from PostFinance reports Errors
     *
     * @param array $responseParams
     * @return mixed
     */
     public function checkResponse($responseParams)
     {
         if ($responseParams['NCERROR'] > 0):
            if (empty($responseParams['NCERRORPLUS'])) {
                $responseParams['NCERRORPLUS'] = Mage::helper('postfinance')->__('Invalid payment information')." Errorcode:".$responseParams['NCERROR'];
            }
            
            //avoid exception if STATUS is set with special values
            if (isset($responseParams['STATUS']) && is_numeric($responseParams['STATUS'])):
                return;
            endif;
            
            Mage::throwException(
                Mage::helper('postfinance')->__('An error occured during the PostFinance request. Your action could not be executed. Message: "%s".',$responseParams['NCERRORPLUS'])
            );
         endif;
     }
}
