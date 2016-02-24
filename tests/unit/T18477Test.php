<?php

use Codeception\Util\Stub;
use AspectMock\Test as am;

/** @noinspection PhpUndefinedClassInspection */
class T18477Test extends \Codeception\TestCase\Test
{
    /**
     * @var \CodeGuy
     */
    protected $codeGuy;

    protected function _before()
    {
    }

    protected function _after()
    {
        am::clean();
    }

    public function testAddAnExistingDomainCancelsAddition()
    {
        set_include_path(realpath(__DIR__ . '/../../scripts'));

        require_once __DIR__ . '/../../scripts/SpamFilter/System.php';
        require_once __DIR__ . '/../../scripts/SpamFilter/Domains.php';

        $domains = new SpamFilter_Domains;

        $domain = 'example.com';

        am::double(
            'Zend_Registry',
            array(
                'set' => null,
                'get' => function () {
                        $result              = new stdClass;
                        $result->apihost     = 'localhost';
                        $result->apiuser     = 'ress';
                        $result->apipass     = 'pass';
                        $result->ssl_enabled = false;

                        return $result;
                    },
            )
        );

        $actionInstance = new SpamFilter_ResellerAPI_Action('domain');
        am::double(
            $actionInstance,
            array(
                '_logLine'     => null,
                '_httpRequest' => '{"messages":{"error":["The domain you\'re trying to add (' . $domain
                    . ') is already exists","Domain already exists.","Failed to add domain \'' . $domain
                    . '\'"]},"result":null}',
            )
        );
        am::double('SpamFilter_ResellerAPI', array('domain' => $actionInstance));

        $addResult = $domains->addDomain($domain, "user@$domain", "mx1.$domain");

        $this->assertFalse($addResult);
    }

}