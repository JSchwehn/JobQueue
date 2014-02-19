<?php
/*
 * This file is part of the JobQueue package.
 *
 * (c) Jens Schwehn <jens@ldk.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @ignore
 */
namespace JobQueue {
    /**
     * Class QueueStorage
     * @package JobQueue
     */
    interface QueueStorage
    {
        /**
         * Returns the name of the Queue.
         *
         * @return mixed
         */
        public function getQueueName();

        /**
         * Sets the Queue name during run time.
         *
         * @param $queueName
         * @return mixed
         */
        public function setQueueName($queueName);

        /**
         * Adds an Job to the message Queue - duh!
         *
         * @param $consumerName String
         * @param $command String
         * @param array $parameters
         *
         * @param bool $test
         * @return int
         */
        public function addJob($consumerName, $command, $parameters = array(), $test = false);

        /**
         * Lists all active Jobs
         *
         * @return mixed
         */
        public function listJobs();

        /**
         * Removes a Job from the Queue based on the given ID
         *
         * @param $jobId
         * @return boolean
         */
        public function deleteJob($jobId);

        /**
         * Returns all jobs for a given Consumer
         *
         * @param $consumerName
         * @return array
         */
        public function getJobsByConsumerName($consumerName);

        /**
         * Returns an array of jobs with a given command.
         *
         * @param String $command
         */
        public function getJobsByCommand($command);

        /**
         * Updates the data filed of a given job
         *
         * @param $jobId
         * @param array $parameters
         * @throws \Exception
         */
        public function updateJob($jobId, $parameters = array());

        /**
         * Change the status of an element. Allowed status are
         *  - ok
         *  - error
         *  - ignore
         * An element with status will not be consumed or deleted.
         *
         * @param $jobId
         * @param string $status
         */
        public function setJobStatus($jobId, $status = 'new');

        /**
         * Get the next element from the queue.
         * You can set the $filter to grep just the module which contains the $filter in the name
         *
         * @param string $filter
         * @return bool|mixed
         */
        public function getNextJob($filter = '');

        /**
         * Returns the number of errors occurred for an element
         *
         * @param $jobId
         * @return int
         */
        public function getErrorCount($jobId);

        /**
         * Increases the error count and supply an error message if one is given. Set Final if no retry is necessary.
         *
         * @param $jobId
         * @param string $errorMessage
         * @param bool $final
         */
        public function increaseError($jobId, $errorMessage = '', $final = false);

        /**
         * To avoid a collision the element will be locked and other processes MUST not
         * access this element while it is locked.
         * An element is locked when a lockedAt date is set and the element status is 'new'
         *
         * @param $jobId
         * @throws \Exception
         */
        public function lockElement($jobId);

        /**
         * To remove the lock the lockedAt timestamp will be set to 0000-00-00 00:00:00
         *
         * @param $jobId
         * @throws \Exception
         */
        public function unlockElement($jobId);

        /**
         * If everything was processed successfully, then the element MUST be removed.
         *
         * @param $jobId
         * @throws \Exception
         */
        public function removeElement($jobId);

        /**
         * Removes expired jobs from the queue.
         *
         * @return mixed
         */
        public function garbageCollection();
    }
}