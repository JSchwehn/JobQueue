<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace jobQueue {
    use PDO;
    use \JobQueue\Consumers\Consumers;
    use Monolog\Logger;
    use Monolog\Handler\StreamHandler;

    class JobConsumer
    {
        private $_config = array();

        /** @var PDO|null */
        private $_db = null;

        public function __construct($config = null, $db = null)
        {
            //handle config voodoo
            if ($config instanceof JobQueueConfig) {
                $this->_config = array_merge($this->_config, $config->getConfig());
            } else {
                $cfgPath = __DIR__ . DIRECTORY_SEPARATOR . 'JobQueueConfig.php';
                if (!is_array($config) && !is_null($config)) {
                    $config[] = $config;
                }
                if (is_readable($cfgPath)) {
                    require_once $cfgPath;
                    if (is_array($config)) {
                        $this->_config = array_merge($this->_config, JobQueueConfig::getConfig(), $config);
                    } else {
                        $this->_config = array_merge($this->_config, JobQueueConfig::getConfig());
                    }

                } else {
                    echo $cfgPath . " is not readable";
                    die('die in ' . __LINE__ . '@' . basename(__FILE__) . "\n");
                }
            }
            if ($db instanceof PDO) {
                $this->_db = $db;
            } else {

                $dsn = 'mysql:host=' . DB_SERVER . ';dbname=' . DB_DATABASE;
                $this->_db = new PDO($dsn, DB_SERVER_USERNAME, DB_SERVER_PASSWORD);
            }
        }

        /**
         * Returns the name of the Queue.
         *
         * @return string
         */
        private function queueName()
        {
            return $this->quoteIdentifier($this->_config['queue']['name']);
        }

        /**
         * Short cut to access the DB driver
         *
         * @return PDO
         */
        private function Db()
        {
            return $this->_db;
        }

        /**
         * Main routine.
         * This routine runs through the message queue and tries to consume every entry.
         *
         */
        public function processElements()
        {
            while ($element = $this->getNextElement()) {
                $this->lockElement($element['id']);
                set_time_limit($this->_config['timeout']);
                try {
                    $consumer = $this->consumerFactory($element['consumerName']);
                    $consumer->$element['command']($element['data']);
                    $this->setJobStatus($element['id']);
                    $this->removeElement($element['id']);
                } catch (\Exception $e) {
                    //todo handle not found elements
                    $this->unlockElement($element['id']);
                    $this->increaseError($element['id'], $e->getMessage());
                    echo $e->getMessage();
                    sleep(1);
                    continue;
                }
            }
        }

        /**
         * Method to create and auto-load Consumer Classes
         *
         * @param $consumerName
         * @return \JobQueue\Consumers\Consumers
         * @throws \Exception
         */
        protected function consumerFactory($consumerName)
        {
            $includeFile = __DIR__ . "/Consumers/" . $consumerName . "/$consumerName.php";
            if (!is_readable($includeFile)) {
                throw new \Exception('Could not load consumer ' . $consumerName);
            }
            require_once $includeFile;
            $class = '\JobQueue' . '\\' . $consumerName . '\\' . $consumerName;
            $consumer = new $class();
            if (!($consumer instanceof \JobQueue\Consumers\Consumers)) {
                throw new \Exception('Unknown Consumer ' . $consumerName);
            }

            return $consumer;
        }

        /**
         * Get the next element from the queue.
         * You can set the $filter to grep just the module which contains the $filter in the name
         *
         * @param string $filter
         * @return bool|mixed
         */
        public function getNextElement($filter = '')
        {
            // Hole alle Daten aus dem Queue, welche noch kein Lock haben und einen errorCount <= maxErrorCount und
            // das ExpireDate (== 0000-00-00 00:00:00 OR > NOW()) SORTIERE NACH DATUM
            $sql = "
                SELECT id, consumerName, command, status, data, errorCount
                FROM " . $this->queueName() . "
                WHERE 1
                    AND lockedAt = '0000-00-00 00:00:00'
                    AND errorCount < ?
                    AND consumerName LIKE ?
                    AND (status = 'new' OR status='error')
                    AND (
                        expireDate = '0000-00-00 00:00:00' OR expireDate > NOW()
                    )
                ORDER BY dateCreated DESC
                LIMIT 1
            ";
            $sth = $this->Db()->prepare($sql);
            $sth->execute(array($this->_config['maxErrorCount'], '%' . $filter . '%'));
            $data = $sth->fetch(PDO::FETCH_ASSOC);
            if (!$data) {
                return false;
            }
            $data['data'] = json_decode($data['data'], true);
            unset($t);

            return $data;
        }

        /**
         * Returns the number of errors occurred for an element
         *
         * @param $elementId
         * @return int
         */
        public function getErrorCount($elementId)
        {
            $sql = "SELECT errorCount FROM " . $this->queueName() . " WHERE id=?";
            $sth = $this->Db()->prepare($sql);
            $sth->execute(array((int)$elementId));

            return (int)$sth->fetchColumn();
        }

        /**
         * Increases the error count and supply an error message if one is given.
         *
         * @param $elementId
         * @param string $errorMessage
         * @throws \Exception
         */
        public function increaseError($elementId, $errorMessage = '')
        {
            $sql = "UPDATE " . $this->queueName() . " SET errorCount = errorCount+1, lastError = ? WHERE id=?";
            $sth = $this->Db()->prepare($sql);
            if (!$sth->execute(array($errorMessage, (int)$elementId))) {
                throw new \Exception('Could not set error.');
            }
        }

        /**
         * Change the status of an element. Allowd status are
         *  - ok
         *  - error
         *  - ignore
         * An element with status will not be consumed or deleted.
         *
         * @param $elementId
         * @param string $status
         * @throws \Exception
         */
        public function setJobStatus($elementId, $status = 'ok')
        {
            $sql = "UPDATE " . $this->queueName() . " SET `status` = ? WHERE id=?";
            $sth = $this->Db()->prepare($sql, array($status, $elementId));
            if (!$sth->execute(array($status, (int)$elementId))) {
                throw new \Exception('Could not set error.');
            }
        }

        /**
         * To avoid a collision the element will be locked and other prosses MUST not access this element while it is locked.
         * An element is locked when a lockedAt date is set and the element status is 'new'
         *
         * @param $elementId
         * @throws \Exception
         */
        public function lockElement($elementId)
        {
            $sql = "UPDATE " . $this->queueName() . " SET lockedAt = NOW() WHERE id = ?";
            $sth = $this->Db()->prepare($sql);

            if (!$sth->execute(array((int)$elementId))) {
                throw new \Exception('Could not lock job ID ' . (int)$elementId);
            }
        }

        /**
         * To remove the lock the lockedAt timestamp will be set to 0000-00-00 00:00:00
         *
         * @param $elementId
         * @throws \Exception
         */
        public function unlockElement($elementId)
        {
            $sql = "UPDATE " . $this->queueName() .
                " SET lockedAt = '0000-00-00 00:00:00' WHERE id = ?";
            $sth = $this->Db()->prepare($sql);
            if (!$sth->execute(array((int)$elementId))) {
                throw new \Exception('Could not unlock job ID ' . (int)$elementId);
            }
        }

        /**
         * If everything was processed successfully, then the element MUST be removed.
         * @param $elementId
         * @throws \Exception
         */
        public function removeElement($elementId)
        {
            $sql = "DELETE FROM " . $this->queueName() . " WHERE id = ? LIMIT 1";
            $sth = $this->Db()->prepare($sql);
            if (!$sth->execute(array((int)$elementId))) {
                throw new \Exception('Could not delete job ID ' . (int)$elementId);
            }
        }

        /**
         * To clean up the queue. Every element which
         *  - expire Date has expired (expireDate <= now())
         *  - the error count is higher than the allowed one (->todo this MUST be logged somewhere)
         *  - the element status is 'ok' and has a lockedAt time set.
         */
        public function garbageCollection()
        {
            //todo
        }

        private function quoteIdentifier($field)
        {
            return "`" . str_replace("`", "``", $field) . "`";
        }
    }
}
