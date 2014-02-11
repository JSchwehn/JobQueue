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
        /** @var \JobQueue\Consumers\Consumers|null */
        protected $parent = null;

        protected $parameters = null;

        public function __construct($parameters = null, \JobQueue\Consumers\Consumers $parent = null)
        {
            $this->parameters = $parameters;
            $this->parent = $parent;
        }

        public function getInitParameters()
        {
            return $this->parameters;
        }


        /** @return \JobQueue\Consumers\Consumers */
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

        protected function checkParameters(array $parameters)
        {
            foreach ($parameters as $parameterName => $parameter) {
                switch ($parameterName) {
                    case 'refId':
                        $this->refId = $parameter;
                        break;
                    case 'mailer':
                        if ($parameter instanceof \PHPMailer) {
                            $this->mailer = $parameter;
                        }
                        break;
                }
            }
        }

        protected function returnAnswer($answer = null)
        {
            return $answer;
        }

        protected function logger($message, $level = 10)
        {
//            if(!is_null($this->logger)) {
//                $this->logger->add($message,$level);
//            }
        }
    }
}
