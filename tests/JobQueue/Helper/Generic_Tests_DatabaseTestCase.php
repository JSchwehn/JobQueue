<?php

/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

abstract class Generic_Tests_DatabaseTestCase extends PHPUnit_Extensions_Database_TestCase
{
    /**
     * Take care that we are using the same $pdo instance during all tests
     *
     * @var null|\PDO
     */
    static protected $pdo = null;
    /**
     * Take care that we are using the same $connection during all tests
     * @var null
     */
    private $conn = null;

    final public function getConnection()
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
                self::$pdo->exec(
                    "CREATE TABLE " . $GLOBALS['DB_SCHEMA'] . "_test LIKE " . $GLOBALS['DB_SCHEMA'] . " ;"
                );
                self::$pdo->exec("ALTER TABLE" . $GLOBALS['DB_SCHEMA'] . "_test ENGINE=MEMORY; ");
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, $GLOBALS['DB_SCHEMA'] . "_test");
        }

        return $this->conn;
    }

    public function tearDown()
    {
        if (self::$pdo) {
            self::$pdo->exec("TRUNCATE TABLE " . $GLOBALS['DB_SCHEMA'] . "_test");
        }
        parent::tearDown();
    }
}