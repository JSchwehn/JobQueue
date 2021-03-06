#!/usr/bin/env php
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

require_once 'bootstrap.php';


$jobs = new \JobQueue\JobConsumer(array(
    'storage' => $store,
    'timeout' => 23,
    'consumerBaseDir' => __DIR__ . '/Consumers/',
    'namespace' => 'JobQueue\\Consumer'
));
$jobs->processElements();

