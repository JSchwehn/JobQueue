<?php
namespace JobQueue\tests;

use JobQueue\JobQueueConfig;

class JobConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var null|JobQueueConfig
     */
    private $fixture = null;


    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function testTestMode()
    {
        $this->assertTrue(JobQueueConfig::testMode);
    }

    public function testGetConfig()
    {
        $cfg = JobQueueConfig::getConfig();
        $this->assertEquals(30, $cfg['timeout']);
    }

    public function testSetParametersConstructor()
    {
        $this->assertInstanceOf(
            'JobQueue\JobQueueConfig',
            $fixture = new JobQueueConfig(array('newValue' => 'foo', 'timeout' => 11))
        );
        $this->assertArrayHasKey('timeout', $cfg = $fixture->getConfig());
        $this->assertEquals(11, $cfg['timeout']);
        $this->assertArrayHasKey('newValue', $cfg = $fixture->getConfig());
        $this->assertEquals('foo', $cfg['newValue']);
    }

    public function testSetParametersStatic()
    {
        $cfg = JobQueueConfig::getConfig(array('newValue' => 'foo', 'timeout' => 11));

        $this->assertArrayHasKey('timeout', $cfg);
        $this->assertEquals(11, $cfg['timeout']);
        $this->assertArrayHasKey('newValue', $cfg);
        $this->assertEquals('foo', $cfg['newValue']);
    }

}