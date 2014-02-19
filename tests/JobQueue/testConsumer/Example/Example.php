<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JobQueue\tests\testConsumer\Example {

    use JobQueue\Consumers;

    class Example extends Consumers
    {
        /** @var array Using a white list to avoid unintentional code calling */
        protected $allowedCommands = array('DoSomething', 'CheckUniverse');

        public function __construct(array $parameters = array())
        {
            parent::__construct($parameters);
        }
    }
}