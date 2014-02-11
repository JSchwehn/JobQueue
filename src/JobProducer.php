<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JobQueue {

    class JobProducer
    {

        private $_config = array();
        private $_queueContainer = null;

        public function __construct($config = null)
        {
            if (is_array($config)) {
                foreach ($config as $key => $value) {
                    if (array_key_exists($key, $this->_config)) {
                        $this->_config[$key] = $value;
                    }
                }
            } elseif ($config instanceof JobQueueConfig) {
                $this->_config = array_merge($this->_config, $config->getConfig());
            } elseif ($config instanceof QueueStorage) {
                $this->_config = array_merge($this->_config, $config->getConfig());
            } else {
                $cfgPath = __DIR__ . 'MessageQueueConfig.php';
                if (is_readable($cfgPath)) {
                    require_once $cfgPath;
                    $this->_config = JobQueueConfig::getConfig();
                }
            }
        }

        private function quoteIdentifier($field)
        {
            return "`" . str_replace("`", "``", $field) . "`";
        }

        /**
         * Returns the name of the Queue.
         *
         * @return string
         */
        public function queueName()
        {
            return $this->quoteIdentifier($this->_config['queue']['name']);
        }

        /**
         * Short cut to access the DB driver
         *
         * @return PDO
         */
        public function Db()
        {
            return $this->_db;
        }

        /**
         * Adds an Job to the message Queue - duh!
         *
         * @param $consumerName String
         * @param $command String
         * @param array $parameters
         *
         * @param bool $test
         * @return int
         */
        public function addJob($consumerName, $command, $parameters = array(), $test = false)
        {
            //todo implement
            if ($test) {
                $status = 'ignore';
            } else {
                $status = 'new';
            }
            $sql = "INSERT INTO " . $this->QueueName() . " SET `consumerName`=?, `command`=?, `data`=?, `status`=?";
            $sth = $this->Db()->prepare($sql);
            $parameters = json_encode($parameters);
            $sth->execute(array($consumerName, $command, $parameters, $status));
            $jobId = $this->Db()->lastInsertId();

            return $jobId;
        }

        public function listJobs()
        {
            $sql = "SELECT id, consumerName, command, status, `data` FROM " . $this->queueName() . '';
            $sth = $this->Db()->prepare($sql);
            $sth->execute();
            $retVal = array();
            while ($job = $sth->fetch(PDO::FETCH_ASSOC)) {
                $retVal[] = array(
                    'consumerName' => $job['consumerName'],
                    'command' => $job['command'],
                    'data' => json_decode($job['data'])
                );
            }

            return $retVal;
        }

        /**
         * @param $jobId
         */
        public function deleteJob($jobId)
        {
            $sql = "DELETE FROM " . $this->queueName() . " WHERE id=?";
            $sth = $this->Db()->prepare($sql);
            $sth->execute(array((int)$jobId));
        }

        public function getJobsByConsumerName($consumerName)
        {
            $sql = "SELECT id, consumerName, command, status, `data` FROM " . $this->queueName(
                ) . ' WHERE consumerName=?';
            $sth = $this->Db()->prepare($sql);
            $sth->execute(array($consumerName));
            $retVal = array();
            while ($job = $sth->fetch(PDO::FETCH_ASSOC)) {
                $retVal[] = array(
                    'consumerName' => $job['consumerName'],
                    'command' => $job['command'],
                    'data' => json_decode($job['data'])
                );
            }

            return $retVal;
        }

        public function getJobsByCommand($command)
        {
            $sql = "SELECT id, consumerName, command, status, `data` FROM " . $this->queueName() . ' WHERE command=?';
            $sth = $this->Db()->prepare($sql);
            $sth->execute(array($command));
            $retVal = array();
            while ($job = $sth->fetch(PDO::FETCH_ASSOC)) {
                $retVal[] = array(
                    'consumerName' => $job['consumerName'],
                    'command' => $job['command'],
                    'data' => json_decode($job['data'])
                );
            }

            return $retVal;
        }

        public function updateJob($jobId, $parameters = array())
        {
            $sql = "UPDATE " . $this->queueName() . " SET `data`=? WHERE id=?";
            $sth = $this->Db()->prepare($sql);
            if (!$sth->execute(array(json_encode($parameters), $jobId))) {
                throw new \Exception('Could not update job #' . (int)$jobId);
            }
        }

        public function setJobStatus($jobId, $status = 'new')
        {
            $sql = "UPDATE " . $this->QueueName() . " SET `status` = ?, lastError = ? WHERE id=?";
            $sth = $this->Db()->prepare($sql);
            if (!$sth->execute(array($status, (int)$jobId))) {
                throw new \Exception('Could not set error.');
            }
        }

    }
}
