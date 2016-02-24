<?php
/*
*************************************************************************
*                                                                       *
* ProSpamFilter2                                                        *
* Bridge between Webhosting panels & SpamExperts filtering				*
*                                                                       *
* Copyright (c) 2010-2011 SpamExperts B.V. All Rights Reserved,         *
*                                                                       *
*************************************************************************
*                                                                       *
* Email: support@spamexperts.com                                        *
* Website: htttp://www.spamexperts.com                                  *
*                                                                       *
*************************************************************************
*                                                                       *
* This software is furnished under a license and may be used and copied *
* only in accordance with the  terms of such license and with the       *
* inclusion of the above copyright notice. No title to and ownership    *
* of the software is  hereby  transferred.                              *
*                                                                       *
* You may not reverse engineer, decompile or disassemble this software  *
* product or software product license.                                  *
*                                                                       *
* SpamExperts may terminate this license if you don't comply with any   *
* of the terms and conditions set forth in our end user                 *
* license agreement (EULA). In such event, licensee agrees to return    *
* licensor  or destroy  all copies of software upon termination of the  *
* license.                                                              *
*                                                                       *
* Please see the EULA file for the full End User License Agreement.     *
*                                                                       *
*************************************************************************
*/

/**
 * SpamPanel API Wrapper Action handler
 *
 * This is the actual "brains" behind the API integration. It gets its orders from the SpamFilter_ResellerAPI
 *
 * @class     SpamFilter_ResellerAPI_Action
 * @category  SpamExperts
 * @package   ProSpamFilter2
 * @author    $Author$
 * @copyright Copyright (c) 2011, SpamExperts B.V., All rights Reserved. (http://www.spamexperts.com)
 * @license   Closed Source
 * @version   2.1
 * @link      http://www.spamexperts.com/wiki/index.php?title=ProSpamFilter2
 * @since     2.1
 *
 * @method string add()
 * @method string set()
 * @method string remove()
 * @method string setproducts()
 * @method string archive()
 * @method string exists(array $arguments)
 */
class SpamFilter_ResellerAPI_Action
{
    /**
     * @param string $_controller API module
     */
    private $_controller;

    /**
     * @param string $_username API Username
     */
    private $_username;

    /**
     * @param string $_password API Password
     */
    private $_password;

    /**
     * @param string $_hostname API Hostname
     */
    private $_hostname;

    /**
     * @param boolean $_sslenabled Use SSL for API calls
     */
    private $_sslenabled;

    /**
     * Constructor
     *
     * @access public
     *
     * @param mixed $controller Controller to work with.
     *
     * @return \SpamFilter_ResellerAPI_Action
     */
    public function __construct($controller)
    {
        $this->_controller = $controller;

        $config = Zend_Registry::get('general_config');
        $this->_hostname = $config->apihost;
        $this->_username = $config->apiuser;
        $this->_password = $config->apipass;
        $this->_sslenabled = $config->ssl_enabled;
    }

    /**
     * Caller Magic, actually does the real API communication and return data converting
     *
     * @access public
     *
     * @param mixed $action
     * @param mixed $params
     *
     * @throws exception
     * @return array
     */
    public function __call($action, $params)
    {
        if (is_array($params)) {
            $params = $params[0]; // 1 array part deeper.
        }

        // Lets prepare parameters for method calling
        $encoded_params = array();
        if (!empty($params)) {
            $idn = new IDNA_Convert;
            foreach ($params as $param_name => $param_value) {
                if (in_array($param_name, array('domain'))) {
                    $this->_logLine("IDN encoding values for '{$param_name}'");
                    $param_value = $idn->encode($param_value, true);
                }

                if (in_array($param_name, array('domain', 'email', 'username'))) {
                    $param_value = function_exists('mb_strtolower')
                        ? mb_strtolower($param_value, 'UTF-8')
                        : strtolower($param_value);
                }

                if (is_array($param_value)) {
                    $this->_logLine("Converting value for '{$param_name}' to an JSON array");
                    // Convert the PHP array to a JSON array.
                    $param_value = Zend_Json::encode($param_value);
                }

                // Put the encoded parameters in to the array we are using for further operations
                $encoded_params[] = rawurlencode(strtolower($param_name)) . '/' . rawurlencode($param_value);
            }
        }

        // Composing URL to request
        $url = ($this->_sslenabled) ? 'https://' : 'http://';
        $url
            .=
            $this->_hostname . '/api/' . $this->_controller . '/' . $action . '/format/json' . (!empty($encoded_params)
                ? '/' . implode('/', $encoded_params) : '');
        $this->_logLine("Going to call: {$url}");

        // Start API request
        $response = $this->_httpRequest($url);

        if ($response === false) // No good response given (404? 500? somekind of error?)
        {
            // Data failed, we received no data / false
            $errors['status'] = false;
            $errors['reason'] = "API_REQUEST_FAILED";
            $errors['additional'] = null;

            return $errors;
        } else {
            if ((stristr($response, ": SQLSTATE")) || (stristr($response, "fatalerror.php"))) {
                throw new exception("API communication failed.");
            }

            //@TODO: Implement proper error handling based on (#10370)
            try {
                $data = Zend_Json::decode($response);
            } catch (Exception $e) {
                throw new exception("Unable to process API response, not in expected format: ".$response);
            }

            if (!is_array($data)) {
                // Data convert failed, probably dit not get JSON fed
                $errors['status'] = false;
                $errors['reason'] = "API_REQUEST_FAILED";
                $errors['additional'] = null;

                return $errors;
            }

            // From this point on we can assume that $data is a multidimensional array containing the information we need.

            // First, some error checking
            if (!empty($data['messages']['error']) && is_array($data['messages']['error'])) {

                // We got an error returned.
                $errors['status'] = false;
                $errors['additional'] = $data['messages']['error'];

                // Provide additional reasons, we have to do this manually since we need #10504 for this to properly work.
                $this->_logLine("Returned errors: " . serialize($data['messages']['error']));
                foreach ($data['messages']['error'] as $errorLine) {
                    if (stristr($errorLine, "already present")) {
                        // Domain already exists (creation)
                        $this->_logLine("Domain already exists");
                        $errors['reason'] = "DOMAIN_EXISTS";
                    } elseif (stristr($errorLine, "Alias already exists")) {
                        // Domain already exists (creation)
                        $this->_logLine("Domain already exists as an alias");
                        $errors['reason'] = "ALIAS_EXISTS";
                    } elseif (stristr($errorLine, "is not registered on") || stristr($errorLine, "No such domain")) {
                        // Domain does not exist (authticket)
                        $errors['reason'] = "DOMAIN_NOT_EXISTS";
                        $this->_logLine("Domain does not exist");
                    } elseif (stristr($errorLine, "no permissions")) {
                        // No permission (e.g. domain list)
                        $errors['reason'] = "NO_PERMISSION";
                        $this->_logLine("No permission to work on domain");
                    } elseif (stristr($errorLine, "Incorrect usage of API")) {
                        // API translations (Spampanel -> Software) apparently did not work. Wrongly provided variables maybe?
                        $errors['reason'] = "API_CORE_ERROR";
                        $this->_logLine("API Core error");
                    } else {
                        // Generic error.
                        $errors['reason'] = "API_UNHANDLED_ERROR";
                        $this->_logLine("Unhandled API error");
                    }
                }

                // Request failed, returning error-array.
                return $errors;
            }

            if (($this->_controller == 'authticket') || (in_array($action, array('get', 'list')))) {

                // Single entries can be converted to a string instead (e.g. version retrieval)
                if (count($data['result']) == 1) {
                    return $data['result'];
                }

                // Return the array data.
                return $data['result'];
            }

            $errors['status'] = true;
            $errors['reason'] = "OK";

            return $errors;
        }
    }

    /**
     * Internal method for running http request
     *
     * @access private
     *
     * @param string $url
     *
     * @return string
     */
    private function _httpRequest($url)
    {
        $config = new stdClass();
        $config->apiuser = $this->_username;
        $config->apipass = $this->_password;

        $contents = SpamFilter_HTTP::getContent($url, $config);

        return $contents;
    }

    /**
     * @param $message
     */
    private function _logLine($message)
    {
        fwrite(STDERR, "[API]" . $message . PHP_EOL);
    }
}
