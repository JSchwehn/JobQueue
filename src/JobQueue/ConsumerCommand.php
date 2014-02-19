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
    abstract class ConsumerCommand
    {
        protected $refId = null;
        protected $mailer = null;
        /** @var \JobQueue\Consumers|null */
        protected $parent = null;

        protected $parameters = null;

        public function __construct($parameters = null, \JobQueue\Consumers $parent = null)
        {
            $this->parameters = $parameters;
            $this->parent = $parent;
        }

        public function getInitParameters()
        {
            return $this->parameters;
        }


        /** @return \JobQueue\Consumers */
        public function Parent()
        {
            return $this->parent;
        }

        /**
         * @param $parameter
         * @param bool $remoteCall
         * @return boolean
         */
        abstract public function execute($parameter, $remoteCall = false);

//        protected function checkParameters(array $parameters)
//        {
//            foreach ($parameters as $parameterName => $parameter) {
//                switch ($parameterName) {
//                    case 'refId':
//                        $this->refId = $parameter;
//                        break;
//                }
//            }
//        }
//
//        protected function returnAnswer($answer = null)
//        {
//            return $answer;
//        }
    }
}
