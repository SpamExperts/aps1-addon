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

    public function testMissingDomainsGetRemoved()
    {
        foreach  (array(
            'SETTINGS_domains_1' => '​testsamedi.com',
            'SETTINGS_provisioned_domains' => '​testsamedi.com,​parallels.info',
        ) as $name => $value) {
            putenv("$name=$value");
        }

        $domainManager = m::mock('SpamFilter_Domains[removeDomain,setServices,updateServices]');
        $domainManager->makePartial()->shouldAllowMockingProtectedMethods();
        $domainManager->shouldReceive('removeDomain')->once()->with('​parallels.info');
        $domainManager->shouldReceive('setServices')->zeroOrMoreTimes()->andReturn(true);
        $domainManager->shouldReceive('updateServices')->zeroOrMoreTimes()->andReturn(true);

        /** @var SpamFilter_Domains $domainManager */
        $domainManager->install();
    }

}