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

require_once 'bootstrap.php';

$producer = new \JobQueue\JobProducer(array('storage' => $store));
$producer->addJob('Example', 'DoSomething', array());