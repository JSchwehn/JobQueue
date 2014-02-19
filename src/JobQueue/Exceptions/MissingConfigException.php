<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JobQueue\Exceptions {
    class MissingConfigException extends \Exception
    {
        public function __construct($message = "", $code = 0, \Exception $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }
}