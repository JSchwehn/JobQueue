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

    use JobQueue\Exceptions\CommandNotFoundException;
    use JobQueue\Exceptions\MissingConfigException;
    use JobQueue\Exceptions\NotFoundException;

    class JobConsumer
    {
        private $_config = array();

        /**
         * @var null|QueueStorage
         */
        private $_queueContainer = null;

        public function __construct($config = null)
        {
            $this->_config = array_merge($config, $this->_config);

            if (isset($this->_config['storage'])) {
                $this->_queueContainer = $this->_config['storage'];
                unset($this->_config['storage']);
            } else {
                throw new MissingConfigException('Need a job container (instance of QueueStorage) in element \'storage\'');
            }

            if (!isset($this->_config['timeout'])) {
                $this->_config['timeout'] = 30; // 30 sec timeout
            }

            if (!isset($this->_config['errorDelay'])) {
                $this->_config['errorDelay'] = 0; // how many sec to sleep between errors
            }
            if (!isset($this->_config['consumerBaseDir'])) {
                $this->_config['consumerBaseDir'] = __DIR__ . "/Consumers/";
            }
            if (!isset($this->_config['namespace'])) {
                $this->_config['namespace'] = '\JobQueue';
            }
        }

        /**
         * @return QueueStorage|null
         */
        protected function Store()
        {
            return $this->_queueContainer;
        }

        /**
         * Returns the path where the consumer modules are located
         * @return String
         */
        public function getConsumerBaseDir()
        {
            return $this->_config['consumerBaseDir'];
        }

        /**
         * Returns how long a consumer is allowed to run in seconds
         * @return int
         */
        public function getTimeout()
        {
            return (int)$this->_config['timeout'];
        }

        /**
         * Returns the time in sec. the script sleeps if an exception is raised
         * @return int
         */
        public function getDelay()
        {
            return (int)$this->_config['errorDelay'];
        }

        /**
         * Return a namespace prefix. Could be used to move the consumer folder where ever you like
         * @return mixed
         */
        public function getNamespace()
        {
            return $this->_config['namespace'];
        }

        /**
         * Main routine.
         * This routine runs through the message queue and tries to consume every entry.
         * After success consuming a entry, the entry will be purged.
         */
        public function processElements()
        {

            while ($element = $this->Store()->getNextJob()) {
                $this->processElement($element);

            }
        }

        /**
         * Just processes one Element.
         *
         * @throws \Exception
         * @throws Exceptions\NotFoundException
         * @throws \Exception
         */
        public function processElement($element = null)
        {
            if ($element == null) {
                $element = $this->Store()->getNextJob();
            }
            if (!isset($element['id'])) {
                throw new NotFoundException('No ID in element was found');
            }
            $this->Store()->lockElement($element['id']);
            set_time_limit($this->getTimeout());
            $retVal = null;
            try {
                $consumer = $this->consumerFactory($element['consumerName']);
                $retVal = $consumer->$element['command']($element['data']);
                $this->Store()->setJobStatus($element['id']); // default value is new
                $this->Store()->removeElement($element['id']);
            } catch (NotFoundException $e) {
                $this->Store()->unlockElement($element['id']);
                $this->Store()->increaseError($element['id'], $e->getMessage(), true);
                throw $e;
            } catch (\Exception $e) {
                $this->Store()->unlockElement($element['id']);
                $this->Store()->increaseError($element['id'], $e->getMessage());
                sleep($this->getDelay());
            }

            return $retVal;
        }

        /**
         * Method to create and auto-load Consumer Classes
         *
         * @param $consumerName
         * @throws NotFoundException
         * @return \JobQueue\Consumers
         */
        protected function consumerFactory($consumerName)
        {
            $includeFile = $this->getConsumerBaseDir() . $consumerName . "/$consumerName.php";
            if (!is_readable($includeFile)) {
                throw new NotFoundException('Could not load consumer ' . $consumerName);
            }
            require_once $includeFile;
            $class = $this->getNamespace() . '\\' . $consumerName . '\\' . $consumerName;
            // load a new consumer - a concrete implementation of Consumers.php
            echo "Loading $class\n";
            $consumer = new $class($this->_config);

            return $consumer;
        }
    }
}
