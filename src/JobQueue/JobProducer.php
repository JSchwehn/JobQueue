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
        /**
         * @var null|QueueStorage
         */
        private $_queueContainer = null;

        public function __construct(array $config = array())
        {
            $this->_config = array_merge($config, $this->_config);
            if (isset($this->_config['storage'])) {
                $this->_queueContainer = $this->_config['storage'];
                unset($this->_config['storage']);
            }
        }

        /**
         * @param $consumerName
         * @param $command
         * @param array $parameters
         * @param bool $testMode
         * @return int
         */
        public function addJob($consumerName, $command, $parameters = array(), $testMode = false)
        {
            if (isset($this->_queueContainer) && $this->_queueContainer instanceof QueueStorage) {
                return $this->_queueContainer->addJob($consumerName, $command, $parameters, $testMode);
            }

            return false;
        }

    }
}
