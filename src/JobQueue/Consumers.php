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
    use JobQueue\Exceptions\NotFoundException;

    abstract class Consumers
    {

        /** @var array Container containing already loaded Commands */
        protected $loadedCommands = array();
        /** @var array Global Memory */
        protected $global = array();
        /** @var array */
        private $_config = array();


        public function __construct(array $parameters = array())
        {
            $this->_config = array_merge($parameters, $this->_config);
            if (!isset($this->_config['consumerBaseDir'])) {
                $this->_config['consumerBaseDir'] = __DIR__ . "/Consumers/";
            }
            if (!isset($this->_config['namespace'])) {
                $this->_config['namespace'] = '\JobQueue';
            }
            if (!isset($this->_config['commandFolder'])) {
                $this->_config['commandFolder'] = '/Commands/';
            }
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
         * @return mixed
         */
        public function getCommandFolder()
        {
            return $this->_config['commandFolder'];
        }

        /**
         * @param $command
         * @param $parameters
         * @param null $className
         * @throws Exceptions\NotFoundException
         * @throws \Exception
         */
        protected function loadCommand($command, $parameters, $className = null)
        {
            if (is_null($className)) {
                $className = $this->getClassName(get_class($this));
                $className = $className['classname'];
            }
            $includeFile = $this->getConsumerBaseDir() . $className . $this->getCommandFolder() . $command . ".php";
            if (isset($this->loadedCommands[$className][$command])) {
                throw new \Exception('Recursion detected.' . $includeFile . ' already exists.');
            }
            if (!is_readable($includeFile)) {
                throw new NotFoundException('Could not load Command ' . $command . ' (not readable) ');
            }
            require_once $includeFile;

            $class = $command;
//            $class = $this->getNamespace() . '\\ConsumerCommand\\' . $className . $this->getCommandFolder() . $command;
            $instance = new $class($parameters, $this);
            $this->loadedCommands[$className][$command] = $instance;
        }

        /**
         * @param $key
         * @return null
         */
        public function getGlobal($key)
        {
            if (!isset($this->global[$key])) {
                return null;
            }

            return $this->global[$key];
        }

        /**
         * Method to access foreign commandos
         *
         * @param $className
         * @param $command
         * @param $parameters
         * @return mixed
         */
        public function callRemoteCommand($className, $command, $parameters)
        {
            $this->loadCommand($command, $parameters, $className);
            $retVal = $this->loadedCommands[$className][$command]->execute($parameters, true);
            $this->removeCommand($command);

            return $retVal;
        }

        /**
         * Removes a command from the command buffer. Used for remoteCall.
         * @param $command
         * @param null $className
         */
        protected function removeCommand($command, $className = null)
        {
            if (is_null($className)) {
                $className = $this->getClassName(get_class($this));
                $className = $className['classname'];
            }
            unset($this->loadedCommands[$className][$command]);
        }

        /**
         * @param $name
         * @param $arguments
         * @return mixed
         * @throws \Exception
         */
        public function __call($name, $arguments)
        {
            $argument = $arguments[0];
            $className = $this->getClassName(get_class($this));
            $className = $className['classname'];
            if (!in_array($name, $this->allowedCommands)) {
                throw new \Exception('Unknown Command ' . $name);
            }
            // Lazy loading
            if (!in_array($name, $this->loadedCommands)) {
                $this->loadCommand($name, $argument);
            }

            return $this->loadedCommands[$className][$name]->execute($argument);
        }

        /**
         * Small helper method to assure that we just the class name and not the namespace.
         *
         * @param $class String
         * @return array
         */
        private function getClassName($class)
        {
            return array(
                'namespace' => array_slice(explode('\\', $class), 0, -1),
                'classname' => join('', array_slice(explode('\\', $class), -1)),
            );
        }
    }
}
