<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use JobQueue\ConsumerCommand;

class DoSomething extends ConsumerCommand
{

    /**
     * @param $parameter
     * @param bool $remoteCall
     * @return boolean
     */
    public function execute($parameter, $remoteCall = false)
    {
        echo "Did something!";

        return true;
    }

}