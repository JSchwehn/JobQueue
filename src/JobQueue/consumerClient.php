#!/usr/bin/php
<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
// bootstrap

include 'JobConsumer.php';
include 'Consumers.php';
include 'ConsumerCommand.php';


$jobs = new JobConsumer(array(
    'semaphore' => miniSemaphore::getInstance(
            'JobConsumer.lock',
            array('maxTime' => 'PT60S')
        )
));
$jobs->processElements();

