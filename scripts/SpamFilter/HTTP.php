<?php
/*
*************************************************************************
*                                                                       *
* ProSpamFilter2                                                        *
* Bridge between Webhosting panels & SpamExperts filtering				*
*                                                                       *
* Copyright (c) 2010 SpamExperts B.V. All Rights Reserved,              *
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
class SpamFilter_HTTP
{
    public static function getContent($url, $config = null, $password = null)
    {
        $version = SpamFilter_Version::getVersion();
        try {
            $obj_client = new Zend_Http_Client( );
            $obj_client->setConfig(array(
                    'useragent' 		=> "ProSpamFilter/" . $version,
                    'timeout'		=> 60
                )
            );

            $adapter = new Zend_Http_Client_Adapter_Socket();
            $obj_client->setAdapter($adapter);
            $streamOpts = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'allow_self_signed' => true
                )
            );
            $adapter->setStreamContext($streamOpts);
            self::_logline("Going to request '{$url}'");
            try{
                $obj_client->setUri( $url );
            } catch (Zend_Uri_Exception $e) {
                self::_logline("Invalid URL supplied");
                return false;
            }
            if( isset($config->apiuser) && (isset($config->apipass) || isset($password)) )
            {
                self::_logline("Doing authenticated HTTP request with username '{$config->apiuser}'");
                if(!empty($password))
                {
                    self::_logline("Using custom password'");
                    $obj_client->setAuth( $config->apiuser, $password );
                } else {
                    self::_logline("Using normal password'");
                    $obj_client->setAuth( $config->apiuser, $config->apipass );
                }
            }

            $obj_client->request( 'GET' );
            $response = $obj_client->getLastResponse();
            $responsecode = $response->getStatus();

            self::_logline("Responsecode: {$responsecode}");
            if( $responsecode != 200 )
            {
                self::_logline( "HTTP request failed with statuscode: {$responsecode}" );
                return false;
            }
            $content = $response->getBody();

            return $content;
        } catch (Zend_Http_Client_Exception $e) {
            self::_logline( 'HTTP request failed:' . $e->getMessage() );

            return false;
        }
    }

    private function _logLine($line)
    {
        fwrite(STDERR, "[HTTP]" . $line . PHP_EOL);
    }
}