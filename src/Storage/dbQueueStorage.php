<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/*
 * Simple storage for the queue messages.
 * Stores the Jobs in a mysql database table. Using the PDO DB abstraction layer
 */
namespace JobQueue\Storage {
    use JobQueue\Exceptions\MissingConfigException;
    use JobQueue\QueueStorage;

    class DbQueueStorage implements QueueStorage
    {
        /** @var \PDO */
        private $_db = null;
        private $_config = array();

        /**
         * If you already using the PDO DB layer in your application just pass the parameter
         * 'pdo' with a reference to your PDO instance. If you don't use PDO you must have to provide
         * a data source name (dsn), the username and password for the db user. In all cases you must provide
         * the name of the table in which the messages should be stored (queueName). The DDL for the queue table looks like
         *
         * Known Parameters
         * - dsn string @see http://php.net/manual/de/ref.pdo-mysql.connection.php
         * - pdo \PDO instance.
         * - queueName This name will be used as table to store the messages
         * - dbUsername Name of the db user. Needed only if a DSN is provided.
         * - dbPassword Password for the db user. Needed only if a DSN is provided.
         * - dbOptions options passed to the PDO layer. Only if a DSN is provided.
         *
         * @param array $parameters
         * @throws \JobQueue\Exceptions\MissingConfigException
         */
        public function __construct(array $parameters)
        {
            $this->_config = array_merge($this->_config, $parameters);
            if (isset($this->_config['pdo']) && $this->_config['pdo'] instanceof \PDO) {
                $this->_db = $this->_config['pdo'];
            } elseif (isset($this->_config['dsn']) && is_string($this->_config['dsn'])) {
                $options = array();
                if (is_array($this->_config['dbOptions'])) {
                    $options = $this->_config['dbOptions'];
                }
                if (!isset($this->_config['dbUsername'])) {
                    throw new MissingConfigException('Database username is missing. A username and password are needed if a DSN is provided.');
                }
                if (!isset($this->_config['dbPassword'])) {
                    throw new MissingConfigException('Database password is missing. A username and password are needed if a DSN is provided.');
                }
                $this->_db = new \PDO($this->_config['dsn'], $this->_config['dbUsername'], $this->_config['password'], $options);
            } else {
                throw new MissingConfigException('Need either a PDO instance (pdo) or a data source name (dsn) to access the database.');
            }
            if (!isset($this->_config['queueName'])) {
                throw new MissingConfigException('Need the name of the queue table to save the messages. queueName is missing');
            }
        }

        /**
         * Return the name of the Queue Name DB safe
         *
         * @return String
         */
        public function getQueueName()
        {
            return $this->quoteIdentifier($this->_config['dbName']);
        }

        /**
         * @param $queueName
         * @return $this DbQueueStorage
         */
        public function setQueueName($queueName)
        {
            $this->_config['queueName'] = $queueName;

            return $this;
        }

        /**
         *
         * @param string $consumerName
         * @param string $command
         * @param array $parameters
         * @param bool $test
         * @return string
         */
        public function addJob($consumerName, $command, $parameters = array(), $test = false)
        {
            if ($test) {
                $status = 'ignore';
            } else {
                $status = 'new';
            }
            $sth = $this->Db()->prepare(
                "INSERT INTO " . $this->getQueueName() . " SET `consumerName`=?, `command`=?, `data`=?, `status`=?"
            );
            $parameters = json_encode($parameters);
            $sth->execute(array($consumerName, $command, $parameters, $status));
            $jobId = $this->Db()->lastInsertId();

            return $jobId;
        }

        /**
         * Returns an array with all jobs
         *
         * @return array
         */
        public function listJobs()
        {
            $sth = $this->Db()->prepare(
                "SELECT `id`, `consumerName`, `command`, `status`, `data` FROM " . $this->getQueueName()
            );
            $sth->execute();
            $retVal = array();
            while ($job = $sth->fetch(\PDO::FETCH_ASSOC)) {
                $retVal[] = array(
                    'consumerName' => $job['consumerName'],
                    'command' => $job['command'],
                    'data' => json_decode($job['data'])
                );
            }

            return $retVal;
        }

        /**
         * Removes a job from the queue
         *
         * @param $jobId
         * @return boolean
         */
        public function deleteJob($jobId)
        {
            $sth = $this->Db()->prepare("DELETE FROM " . $this->getQueueName() . " WHERE id=?");

            return $sth->execute(array((int)$jobId));
        }

        /**
         * Returns all jobs for a given Consumer
         *
         * @param $consumerName
         * @return array
         */
        public function getJobsByConsumerName($consumerName)
        {
            $sth = $this->Db()->prepare(
                "SELECT id, consumerName, command, status, `data` FROM " . $this->getQueueName(
                ) . ' WHERE consumerName=?'
            );
            $sth->execute(array($consumerName));
            $retVal = array();
            while ($job = $sth->fetch(\PDO::FETCH_ASSOC)) {
                $retVal[] = array(
                    'consumerName' => $job['consumerName'],
                    'command' => $job['command'],
                    'data' => json_decode($job['data'])
                );
            }

            return $retVal;
        }

        /**
         * Returns an array of jobs with a given command.
         *
         * @param String $command
         */
        public function getJobsByCommand($command)
        {
            $sth = $this->Db()->prepare(
                "SELECT id, consumerName, command, status, `data` FROM " . $this->getQueueName() . ' WHERE command=?'
            );
            $sth->execute(array($command));
            $retVal = array();
            while ($job = $sth->fetch(\PDO::FETCH_ASSOC)) {
                $retVal[] = array(
                    'consumerName' => $job['consumerName'],
                    'command' => $job['command'],
                    'data' => json_decode($job['data'])
                );
            }
        }

        /**
         * Updates the data filed of a given job
         *
         * @param $jobId
         * @param array $parameters
         * @throws \Exception
         */
        public function updateJob($jobId, $parameters = array())
        {
            $sth = $this->Db()->prepare("UPDATE " . $this->getQueueName() . " SET `data`=? WHERE id=?");
            if (!$sth->execute(array(json_encode($parameters), $jobId))) {
                throw new \Exception('Could not update job #' . (int)$jobId);
            }
        }

        /**
         * Set a new status for a given job
         *
         * @param $jobId
         * @param string $status
         * @throws \Exception
         */
        public function setJobStatus($jobId, $status = 'new')
        {
            $sth = $this->Db()->prepare(
                "UPDATE " . $this->getQueueName() . " SET `status` = ?, lastError = ? WHERE id=?"
            );
            if (!$sth->execute(array($status, (int)$jobId))) {
                throw new \Exception('Could not set error.');
            }
        }

        /**
         * Get the next element from the queue.
         * You can set the $filter to grep just the module which contains the $filter in the name.
         * Will returns the oldest entry first.
         *
         * Get all jobs which are not locked, an error count less than maxErrorCount and the
         * expireDate is equal '0000-00-00 00:00:00' or greater than the current date.
         *
         * @param string $filter
         * @return bool|mixed
         */
        public function getNextJob($filter = '')
        {
            $sql = "
                SELECT id, consumerName, command, status, data, errorCount
                FROM " . $this->getQueueName() . "
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
            $data = $sth->fetch(\PDO::FETCH_ASSOC);
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
         * @param $jobId
         * @return int
         */
        public function getErrorCount($jobId)
        {
            $sth = $this->Db()->prepare("SELECT errorCount FROM " . $this->getQueueName() . " WHERE id=?");
            $sth->execute(array((int)$jobId));

            return (int)$sth->fetchColumn();
        }

        /**
         * Increases the error count and supply an error message if one is given.
         *
         * @param $jobId
         * @param string $errorMessage
         * @throws \Exception
         */
        public function increaseError($jobId, $errorMessage = '')
        {
            $sth = $this->Db()->prepare(
                "UPDATE " . $this->getQueueName() . " SET errorCount = errorCount+1, lastError = ? WHERE id=?"
            );
            if (!$sth->execute(array($errorMessage, (int)$jobId))) {
                throw new \Exception('Could not set error for job ID: ' . $jobId);
            }
        }

        /**
         * To avoid a collision the element will be locked and other processes MUST not
         * access this element while it is locked.
         * An element is locked when a lockedAt date is set and the element status is 'new'
         *
         * @param $jobId
         * @throws \Exception
         */
        public function lockElement($jobId)
        {
            $sth = $this->Db()->prepare("UPDATE " . $this->getQueueName() . " SET lockedAt = NOW() WHERE id = ?");
            if (!$sth->execute(array((int)$jobId))) {
                throw new \Exception('Could not lock job ID ' . (int)$jobId);
            }
        }

        /**
         * To remove the lock the lockedAt timestamp will be set to 0000-00-00 00:00:00
         *
         * @param $jobId
         * @throws \Exception
         */
        public function unlockElement($jobId)
        {
            $sth = $this->Db()->prepare(
                "UPDATE " . $this->getQueueName() . " SET lockedAt = '0000-00-00 00:00:00' WHERE id = ?"
            );
            if (!$sth->execute(array((int)$jobId))) {
                throw new \Exception('Could not unlock job ID ' . (int)$jobId);
            }
        }

        /**
         * If everything was processed successfully, then the element MUST be removed.
         *
         * @param $jobId
         * @throws \Exception
         */
        public function removeElement($jobId)
        {
            $sth = $this->Db()->prepare("DELETE FROM " . $this->getQueueName() . " WHERE id = ? LIMIT 1");
            if (!$sth->execute(array((int)$jobId))) {
                throw new \Exception('Could not delete job ID ' . (int)$jobId);
            }
        }

        /**
         * Removes expired jobs from the queue.
         *
         * @throws \Exception
         * @return mixed
         */
        public function garbageCollection()
        {
            $sth = $this->Db()->prepare("DELETE FROM " . $this->getQueueName() . " WHERE  expireDate < NOW() ");
            if (!$sth->execute(array())) {
                throw new \Exception('Could not remove trash from queue');
            }
        }

        /**
         * Build mysql quotes around a given string
         *
         * @param $field
         * @return string
         */
        private function quoteIdentifier($field)
        {
            return "`" . str_replace("`", "``", $field) . "`";
        }

        /**
         * Short cut to access the DB driver
         *
         * @return \PDO
         */
        private function Db()
        {
            return $this->_db;
        }
    }
}