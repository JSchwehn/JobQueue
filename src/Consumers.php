<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JobQueue\Consumers {
    abstract class Consumers
    {

        /** @var array Container containing already loaded Commands */
        protected $loadedCommands = array();
        /** @var array Global Memory */
        protected $global = array();

        /**
         * @param $command
         * @param $parameters
         * @param null $className
         * @throws \Exception
         */
        protected function loadCommand($command, $parameters, $className = null)
        {
            if (is_null($className)) {
                $className = $this->getClassName(get_class($this));
                $className = $className['classname'];
            }
            $includeFile = __DIR__ . "/Consumers/" . $className . "/Commands/" . $command . ".php";
            if (isset($this->loadedCommands[$className][$command])) {
                throw new \Exception('Recursion detected.' . $includeFile . ' already exists.');
            }
            if (!is_readable($includeFile)) {
                throw new \Exception('Could not load Command ' . $command . ' (not readable) ');
            }
            require_once $includeFile;
            $class = '\JobQueue\\' . $className . '\Command\\' . $command;
            $instance = new $class($parameters, $this);
            if (!$instance instanceof \JobQueue\ConsumerCommand) {
                throw new \Exception('Could not load Command ' . $command . ' wrong instance (' . get_class(
                        $instance
                    ) . ')');
            }
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
            $this->removeCommand($command, $className);

            return $retVal;
        }

        /**
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

            $retVal = $this->loadedCommands[$className][$name]->execute($argument);

            return $retVal;
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
