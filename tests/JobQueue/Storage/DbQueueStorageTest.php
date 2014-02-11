<?php
/**
 * Created by PhpStorm.
 * User: JSchwehn
 * Date: 11.02.14
 * Time: 15:30
 */

namespace JobQueue\Storage;


class DbQueueStorageTest extends \PHPUnit_Framework_TestCase
{

    public function setup()
    {
        $foo = new DbQueueStorage(array());
    }

    public function testGetQueueName()
    {
        $this->assertTrue(true);
    }
}
 