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
    class JobQueueConfig
    {
        const testMode = true;
        static private $_config = array(
            'maxErrorCount' => 3,
            'timeout' => 30,
            'queue' => array(
                'name' => 'jobQueue',
                'filter' => array()
            )
        );

        static public function getConfig()
        {
            return self::$_config;
        }

    }
}
