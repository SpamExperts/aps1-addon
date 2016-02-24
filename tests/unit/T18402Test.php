<?php

use AspectMock\Test as am;

/** @noinspection PhpUndefinedClassInspection */
class T18402Test extends \Codeception\TestCase\Test
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

    public function testProvisioningWithMXRecords()
    {
        require_once __DIR__ . '/../../scripts/SpamFilter/System.php';
        require_once __DIR__ . '/../../scripts/SpamFilter/Domains.php';
        require_once __DIR__ . '/../../scripts/SpamFilter/IDNA_Convert.php';

        am::double(
            'SpamFilter_System',
            [
                'print_stderr' => null,
                '__construct'  => null,
                'getApi'       => am::spec('Dummy')->construct(),
            ]
        );
        $o = am::double(
            'SpamFilter_Domains',
            [
                'getProvisionDomains' => ['example.com' => ['mx' => 'mx.example.com']],
                'getOldDomains'       => [],
                'addDomain'           => true,
            ]
        );

        $d = new SpamFilter_Domains;
        $installResult = $d->install();

        $this->assertEquals(true, $installResult);
        $o->verifyInvoked('addDomain');
    }

    public function testProvisioningWithoutMXRecords()
    {
        require_once __DIR__ . '/../../scripts/SpamFilter/System.php';
        require_once __DIR__ . '/../../scripts/SpamFilter/Domains.php';

        am::double('SpamFilter_System', ['print_stderr' => null, '__construct' => null]);
        am::double('IDNA_Convert', ['encode' => 'somedomain.net']);
        $o = am::double(
            'SpamFilter_Domains',
            [
                'getProvisionDomains' => ['example.com' => []],
                'getOldDomains'       => [],
            ]
        );

        $d = new SpamFilter_Domains;

        try {
            $d->install();
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(10023, $e->getCode());
            $o->verifyNeverInvoked('addDomain');
        }
    }

}