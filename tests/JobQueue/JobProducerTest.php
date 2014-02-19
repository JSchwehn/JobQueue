<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JobQueue\tests;

use JobQueue\JobProducer;

require_once __DIR__ . '/Helper/mockPDO.php';

class JobProducerTest extends \PHPUnit_Framework_TestCase
{
    public function testAddJob()
    {
        $dbStorageMock = $this->getMockBuilder('JobQueue\Storage\DbStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $dbStorageMock->expects($this->once())->method('addJob')->will($this->returnValue(1));
        $fixture = new JobProducer(array('storage' => $dbStorageMock));
        $this->assertEquals(1, $fixture->addJob('test', 'testMe', array()));
    }

    public function testAddJobError()
    {
        $fixture = new JobProducer(array('storage' => null));
        $this->assertFalse($fixture->addJob('test', 'testMe', array()));
    }


}