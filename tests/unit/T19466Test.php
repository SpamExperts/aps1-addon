<?php

use \Mockery as m;

/** @noinspection PhpUndefinedClassInspection */
class T18705Test extends \Codeception\TestCase\Test
{
    /**
     * @var \CodeGuy
     */
    protected $codeGuy;

    protected function _before()
    {
        set_include_path(realpath(__DIR__ . '/../../scripts'));

        require_once __DIR__ . '/../../scripts/SpamFilter/System.php';
        require_once __DIR__ . '/../../scripts/SpamFilter/Domains.php';
        require_once __DIR__ . '/../../scripts/SpamFilter/IDNA_Convert.php';
    }

    protected function _after()
    {
        \Mockery::close();
    }

    public function testNewDomainsGetAdded()
    {
        foreach  (array(
            'SETTINGS_domains_1' => 'example.com',
            'SETTINGS_provisioned_domains' => '',
            'DNS_MX1_SUBSTITUTE_example.com_1' => 'mx.example.com',
        ) as $name => $value) {
            putenv("$name=$value");
        }

        $domainManager = m::mock('SpamFilter_Domains[addDomain,setServices,updateServices]');
        $domainManager->makePartial()->shouldAllowMockingProtectedMethods();
        $domainManager->shouldReceive('addDomain')->once()->with('example.com', '', 'mx.example.com')->andReturn(true);
        $domainManager->shouldReceive('setServices')->zeroOrMoreTimes()->andReturn(true);
        $domainManager->shouldReceive('updateServices')->zeroOrMoreTimes()->andReturn(true);

        /** @var SpamFilter_Domains $domainManager */
        $domainManager->install();
    }

}