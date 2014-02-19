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

require_once __DIR__ . '/Helper/mockPDO.php';
use JobQueue\Storage\DbStorage;

/**
 * Class StorageTest
 * @package JobQueue\tests
 */
class StorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var null|DbStorage
     */
    private $fixture = null;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $PDOMokup = null;
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    private $PDOStatement = null;

    private $cfg = null;
    private $dataFixture = null;

    public function setUp()
    {
        $this->PDOMokup = $this->getMock('mockPDO', array('prepare', 'execute', 'lastInsertId'));
        $this->PDOStatement = $this->getMock('\PDOStatement');
        $this->dataFixture = array(
            array(
                'consumerName' => 'UnitTest',
                'command' => 'testing',
                'maxErrorCount' => 'testing',
                'data' => json_encode(array('foo' => 'bar'))
            ),
            array(
                'consumerName' => 'UnitTest',
                'command' => 'Check Universum',
                'data' => json_encode(array('Answer' => '42'))
            )
        );
        $this->cfg = array(
            'maxErrorCount' => 3,
            'pdo' => $this->PDOMokup,
            'queueName' => $GLOBALS['DB_SCHEMA'] . "_test"
        );
    }

    public function tearDown()
    {
        $this->fixture = $this->PDOMokup = $this->PDOStatement = null;
        parent::tearDown();
    }

    public static function yieldNthResult($name, $results)
    {
        static $indexes = array();
        if (isset($indexes[$name])) {
            $index = $indexes[$name] + 1;
        } else {
            $index = 0;
        }
        if (isset($results[$index])) {
            $indexes[$name] = $index;

            return $results[$index];
        }

        return null;
    }


    public function testConstructNoPDO()
    {
        $cfg = array(
            'dsn' => 'sqlite::memory:',
            'dbUsername' => 'foo',
            'dbPassword' => 'foo',
        );
        $this->setExpectedException(
            'JobQueue\Exceptions\MissingConfigException',
            'Need a PDO instance (pdo) to access the database.'
        );
        $foo = new DbStorage($cfg);
        unset($foo);
    }

    public function testConstructNoQueueName()
    {
        $cfg = $this->cfg;
        unset($cfg['queueName']);
        $this->setExpectedException(
            'JobQueue\Exceptions\MissingConfigException',
            'Need the name of the queue table to save the messages. queueName is missing'
        );
        $foo = new DbStorage($cfg);
    }

    public function testGetQueueName()
    {
        $fixture = new DbStorage($this->cfg);
        $this->assertEquals('`' . $GLOBALS['DB_SCHEMA'] . "_test" . '`', $fixture->getQueueName());
    }

    public function testSetQueueName()
    {
        $fixture = new DbStorage($this->cfg);
        $this->assertInstanceOf('JobQueue\QueueStorage', $fixture->setQueueName('foo'));
    }

    public function testAddJob()
    {
        $this->PDOMokup->expects($this->once())->method('prepare')->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())->method('execute')->will($this->returnValue(true));
        $this->PDOMokup->expects($this->once())->method('lastInsertId')->will($this->returnValue(2));
        $fixture = new DbStorage($this->cfg);
        $this->assertEquals(2, $fixture->addJob('testConsumer', 'unitTest', array('empty')));
    }


    public function testAddJobTest()
    {
        $this->PDOMokup->expects($this->once())->method('prepare')->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())->method('execute')->will($this->returnValue(true));
        $this->PDOMokup->expects($this->once())->method('lastInsertId')->will($this->returnValue(2));
        $fixture = new DbStorage($this->cfg);
        $this->assertEquals(2, $fixture->addJob('testConsumer', 'unitTest', array('empty'), true));
    }

    public function testListJobs()
    {
        $data = $this->dataFixture;
        $this->PDOMokup->expects($this->once())->method('prepare')->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects(
            $this->any()
        )
            ->method('fetch')
            ->will(
                $this->returnCallback(
                    function () use ($data) {
                        return self::yieldNthResult('testListJobs', $data);
                    }
                )
            );

        $fixture = new DbStorage($this->cfg);
        $result = $fixture->listJobs();

        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('consumerName', $result[0]);
        $this->assertArrayHasKey('command', $result[0]);
        $this->assertArrayHasKey('data', $result[0]);
    }

    public function testDeleteJob()
    {
        $this->PDOMokup->expects(
            $this->once()
        )
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects(
            $this->once()
        )
            ->method('execute')
            ->will($this->returnValue(true));
        $fixture = new DbStorage($this->cfg);
        $this->assertInstanceOf('JobQueue\QueueStorage', $fixture->deleteJob(1));
    }

    public function testDeleteJobError()
    {
        $this->PDOMokup->expects(
            $this->once()
        )
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects(
            $this->once()
        )
            ->method('execute')
            ->will($this->returnValue(false));
        $fixture = new DbStorage($this->cfg);
        $this->setExpectedException('Exception', 'Could not delete job #1');
        $fixture->deleteJob(1);
    }

    public function testGetJobsByConsumerName()
    {
        $data = $this->dataFixture;
        $this->PDOMokup->expects($this->once())->method('prepare')->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects(
            $this->any()
        )
            ->method('fetch')
            ->will(
                $this->returnCallback(
                    function () use ($data) {
                        return self::yieldNthResult('testGetJobsByConsumerName', $data);
                    }
                )
            );

        $fixture = new DbStorage($this->cfg);
        $result = $fixture->getJobsByConsumerName('UnitTest');
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('consumerName', $result[0]);
        $this->assertArrayHasKey('command', $result[0]);
        $this->assertArrayHasKey('data', $result[0]);
    }

    public function testGetJobsByCommand()
    {
        $data = $this->dataFixture;
        $this->PDOMokup->expects($this->once())->method('prepare')->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects(
            $this->any()
        )
            ->method('fetch')
            ->will(
                $this->returnCallback(
                    function () use ($data) {
                        return self::yieldNthResult('testGetJobsByCommand', $data);
                    }
                )
            );

        $fixture = new DbStorage($this->cfg);
        $result = $fixture->getJobsByCommand('UnitTest');
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('consumerName', $result[0]);
        $this->assertArrayHasKey('command', $result[0]);
        $this->assertArrayHasKey('data', $result[0]);
    }

    public function testUpdateJob()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(true));
        $fixture = new DbStorage($this->cfg);

        $this->assertInstanceOf('JobQueue\QueueStorage', $fixture->updateJob(1, array('Answer' => 23)));
    }

    public function testUpdateJobException()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));

        $fixture = new DbStorage($this->cfg);
        $this->setExpectedException('Exception', 'Could not update job #1');
        $this->getExpectedException($fixture->updateJob(1, array('Answer' => 23)));
    }

    public function testSetJobStatus()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(true));
        $fixture = new DbStorage($this->cfg);
        $this->assertInstanceOf('JobQueue\QueueStorage', $fixture->setJobStatus(1, 'ok'));
    }

    public function testSetJobStatusException()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));

        $fixture = new DbStorage($this->cfg);
        $this->setExpectedException('Exception', 'Could not change job status id #1');
        $fixture->setJobStatus(1, 'ok');
    }

    public function testGetNextJob()
    {
        $data = array(
            array(
                'consumerName' => 'UnitTest',
                'errorCount' => null,
                'command' => 'testing',
                'data' => json_encode(array('foo' => 'bar'))
            )
        );
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));
        $this->PDOStatement->expects($this->any())
            ->method('fetch')
            ->will(
                $this->returnCallback(
                    function () use ($data) {
                        return self::yieldNthResult(__FUNCTION__, $data);
                    }
                )
            );
        $fixture = new DbStorage($this->cfg);
        $this->assertInternalType('array', $fixture->getNextJob());
    }

    public function testGetNextJobError()
    {
        $data = null;
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));
        $this->PDOStatement->expects($this->any())
            ->method('fetch')
            ->will(
                $this->returnCallback(
                    function () use ($data) {
                        return self::yieldNthResult(__FUNCTION__, $data);
                    }
                )
            );
        $fixture = new DbStorage($this->cfg);
        $this->assertFalse($fixture->getNextJob());
    }

    public function testGetErrorCount()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(0));

        $fixture = new DbStorage($this->cfg);
        $this->assertEquals(0, $fixture->getErrorCount(1));
    }

    public function testGetErrorCountError()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));

        $fixture = new DbStorage($this->cfg);
        $this->setExpectedException(
            'Exception',
            'Could not get error count for job ID: #1'
        );
        $fixture->getErrorCount(1);
    }

    public function testIncreaseErrorError()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));

        $fixture = new DbStorage($this->cfg);
        $this->setExpectedException(
            'Exception',
            'Could not set error for job ID: #1 Message was: Can I haz eror mezage?'
        );
        $fixture->increaseError(1, 'Can I haz eror mezage?');
    }

    public function testIncreaseError()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(true));

        $fixture = new DbStorage($this->cfg);
        $this->assertInstanceOf('JobQueue\QueueStorage', $fixture->increaseError(1, 'Can I haz eror mezage?'));
    }

    public function testLockElement()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(true));
        $fixture = new DbStorage($this->cfg);
        $this->assertInstanceOf('JobQueue\QueueStorage', $fixture->lockElement(1));
    }

    public function testLockElementError()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));

        $fixture = new DbStorage($this->cfg);
        $this->setExpectedException('Exception', 'Could not lock job ID #1');
        $fixture->lockElement(1);
    }

    public function testUnlockElement()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(true));
        $fixture = new DbStorage($this->cfg);
        $this->assertInstanceOf('JobQueue\QueueStorage', $fixture->unlockElement(1));
    }

    public function testUnlockElementError()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));

        $fixture = new DbStorage($this->cfg);
        $this->setExpectedException('Exception', 'Could not unlock job ID #1');
        $fixture->unlockElement(1);
    }

    public function testRemoveElement()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(true));
        $fixture = new DbStorage($this->cfg);
        $this->assertInstanceOf('JobQueue\QueueStorage', $fixture->removeElement(1));
    }

    public function testRemoveElementError()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));

        $fixture = new DbStorage($this->cfg);
        $this->setExpectedException('Exception', 'Could not delete job ID #1');
        $fixture->removeElement(1);
    }

    public function testGarbageCollection()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(true));
        $fixture = new DbStorage($this->cfg);
        $this->assertInstanceOf('JobQueue\QueueStorage', $fixture->garbageCollection());
    }

    public function testGarbageCollectionError()
    {
        $this->PDOMokup
            ->expects($this->once())
            ->method('prepare')
            ->will($this->returnValue($this->PDOStatement));
        $this->PDOStatement->expects($this->once())
            ->method('execute')
            ->will($this->returnValue(false));

        $fixture = new DbStorage($this->cfg);
        $this->setExpectedException('Exception', 'Could not remove trash from queue');
        $fixture->garbageCollection();
    }

}