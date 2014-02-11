#!/usr/bin/php
<?php
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

