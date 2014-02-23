<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__ . '/../../vendor/autoload.php';


$pdo = new \PDO('mysql:dbname=JobQueue;host=localhost', 'testuser', 'testuser');
$store = new \JobQueue\Storage\DbStorage(array('queueName' => 'JobQueue', 'pdo' => $pdo));