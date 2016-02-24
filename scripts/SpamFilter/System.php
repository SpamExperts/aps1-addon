<?php

class SpamFilter_System
{
	public $_api;

	public function __construct()
	{
		// Load the config, needed basically all the time
        $scriptsDir = realpath(dirname(__FILE__) . '/../');
		require_once $scriptsDir . '/SpamFilter/IDNA_Convert.php';
		require_once $scriptsDir . '/Zend/Exception.php';
		require_once $scriptsDir . '/Zend/Registry.php';
		require_once $scriptsDir . '/Zend/Http/Client/Adapter/Socket.php';
		require_once $scriptsDir . '/Zend/Http/Client.php';
        require_once $scriptsDir . '/SpamFilter/Version.php';
		require_once $scriptsDir . '/SpamFilter/HTTP.php';
		require_once $scriptsDir . '/Zend/Json.php';
		require_once $scriptsDir . '/SpamFilter/ResellerAPI/Action.php';
		require_once $scriptsDir . '/SpamFilter/ResellerAPI.php';

		$config = new stdClass();
		$config->apihost 		= getenv('SETTINGS_apihost');
		$config->apiuser 		= getenv('SETTINGS_apiuser');
		$config->apipass 		= getenv('SETTINGS_apipass');
		$config->ssl_enabled 	= getenv('SETTINGS_ssl_enabled');

		// Write config to registry, since the API uses that directly to obtain its data.
        /** @noinspection PhpUndefinedClassInspection */
        Zend_Registry::set('general_config', $config);

		// Init the handle here, so it has the registry data.
        /** @noinspection PhpUndefinedClassInspection */
        $this->_api = new SpamFilter_ResellerAPI();
	}

	public function getApi()
	{
		// Return the API
		return $this->_api;
	}

	public function report_settings( $settings )
	{
		$this->print_stderr( "Going to report: " . serialize($settings) . " to APS" );
		$xw = new XMLWriter;
		$xw->openMemory();
		$xw->setIndent(true);
		$xw->setIndentString(' ');
			$xw->startDocument( '1.0');
				$xw->startElement('output');
					$xw->writeAttribute('xmlns', 'http://apstandard.com/ns/1/configure-output');
						$xw->startElement('settings');
							foreach ( $settings as $setting => $value ) 
							{
								$xw->startElement('setting');
									$xw->writeAttribute('id', $setting);
									$xw->writeElement('value', $value);
								$xw->endElement();
							}
						$xw->endElement();
				$xw->endElement();
			$xw->endDocument();
		echo $xw->outputMemory();	
	}



	public function show_resources( $resources )
	{
		$this->print_stderr( "Going to report: " . serialize($resources) . " to APS" );
		$xw = new XMLWriter;
		$xw->openMemory();
		$xw->setIndent(true);
		$xw->setIndentString(' '); 
			$xw->startDocument( '1.0');
				$xw->startElement('resources');
					$xw->writeAttribute('xmlns', 'http://apstandard.com/ns/1/resource-output');
					foreach ( $resources as $resource => $value ) 
					{
						$xw->startElement('resource');
							$xw->writeAttribute('id', $resource);
							$xw->writeAttribute('value', $value);
						$xw->endElement();
					}
				$xw->endElement();
			$xw->endDocument();
			echo $xw->outputMemory();
	}

	public function print_stderr( $message )
	{
	    fwrite(STDERR, $message . PHP_EOL);
	}	
}
