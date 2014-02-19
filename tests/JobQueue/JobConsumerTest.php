<?php
namespace JobQueue\tests;

use JobQueue\JobConsumer;

class JobConsumerTest extends \PHPUnit_Framework_TestCase
{
    private $dataFixture = null;

    public function setUp()
    {
        $this->dataFixture = array(
            array(
                'id' => 1,
                'consumerName' => 'Example',
                'command' => 'DoSomething',
                'data' => json_encode(array('foo' => 'bar'))
            ),
            array(
                'id' => 2,
                'consumerName' => 'Example',
                'command' => 'CheckUniverse',
                'data' => json_encode(array('Answer' => '42'))
            )
        );
        parent::setUp();
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

    private function buildMock($data = null, $calledMethod)
    {
        if (is_null($data)) {
            $data = $this->dataFixture;
        }
        $store = $this->getMockBuilder('JobQueue\JobConsumer')
            ->disableOriginalConstructor()
            ->setMethods(
                array('getNextJob', 'lockElement', 'setJobStatus', 'removeElement', 'unlockElement', 'increaseError')
            )
            ->getMock();
        $store->expects($this->any())
            ->method('getNextJob')
            ->will(
                $this->returnCallback(
                    function () use ($data, $calledMethod) {
                        return self::yieldNthResult($calledMethod, $data);
                    }
                )
            );

        return $store;
    }

    public function testProcessElements()
    {
        $store = $this->buildMock(null, __FUNCTION__);
        $store->expects($this->exactly(2))->method('lockElement');
//        $store->expects($this->exactly(2))->method('increaseError');
        $consumer = new JobConsumer(array(
            'storage' => $store,
            'timeout' => 23,
            'consumerBaseDir' => __DIR__ . '/testConsumer/',
            'namespace' => 'JobQueue\\tests\\testConsumer'
        ));
        $consumer->processElements();
    }

    public function testProcessElementsError()
    {
        $this->dataFixture = array(
            array(
                'id' => 2,
                'consumerName' => 'Eroor',
                'command' => 'Kwoom',
                'data' => json_encode(array('NoBrains' => 'P=NN'))
            ),
            array(
                'id' => 2,
                'consumerName' => 'Eroor3',
                'command' => 'Kwoom',
                'data' => json_encode(array('NoBrains' => 'P=NN'))
            )
        );
        $store = $this->buildMock(null, __FUNCTION__);
        $store->expects($this->exactly(1))->method('lockElement');
        $store->expects($this->once())->method('increaseError');
        $consumer = new JobConsumer(array(
            'storage' => $store,
            'timeout' => 23,
            'consumerBaseDir' => __DIR__ . '/testConsumer/',
            'namespace' => 'JobQueue\\tests\\testConsumer'
        ));
        $this->setExpectedException('JobQueue\Exceptions\NotFoundException', 'Could not load consumer Eroor');
        $consumer->processElement();
    }


    public function testProcessElementsNoId()
    {
        $this->dataFixture = array(
            array(

                'consumerName' => 'Example',
                'command' => 'CheckUniverse',
                'data' => json_encode(array('Answer' => '42'))
            )
        );
        $store = $this->buildMock(null, __FUNCTION__);
        $consumer = new JobConsumer(array('storage' => $store, 'timeout' => 23));
        $this->setExpectedException('Exception', 'No ID in element was found');
        $consumer->processElements();
    }

    public function testSetConfigDefaultParameter()
    {
        $store = $this->buildMock(null, __FUNCTION__);
        $consumer = new JobConsumer(array('storage' => $store));
        $this->assertEquals(30, $consumer->getTimeout());
    }

    public function testRemoteCall()
    {
        $this->dataFixture = array(
            array(
                'id' => 1,
                'consumerName' => 'Example',
                'command' => 'CheckUniverse',
                'data' => json_encode(array('Answer' => '42'))
            )
        );
        $store = $this->buildMock(null, __FUNCTION__);
        $consumer = new JobConsumer(array(
            'storage' => $store,
            'timeout' => 23,
            'consumerBaseDir' => __DIR__ . '/testConsumer/',
            'namespace' => 'JobQueue\\tests\\testConsumer'
        ));
        $result = $consumer->processElement();
        $this->assertArrayHasKey('foo', $result);
        $this->assertEquals('bar', $result['foo']);
    }
}