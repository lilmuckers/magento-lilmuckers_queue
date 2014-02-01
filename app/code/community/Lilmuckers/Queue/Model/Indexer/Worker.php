<?php

/**
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Tim Vroom <tim@timroom.nl>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Model_Indexer_Worker extends Lilmuckers_Queue_Model_Worker_Abstract
{
    /**
     * Execute the queued index tasks
     *
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     *
     * @return int
     */
    public function run($task)
    {
        $helper = Mage::helper('lilqueue/indexer');

        try {
            $process = Mage::getModel('index/process');
            $data    = $task->getData();
            if (isset($data['event'])) {
                $process->setData($data['data']);
                $process->processEventJob($data['event']);
            } else {
                $process->setData($data);
                $process->reindexAllJob($task);
            }
            $task->success();
        } catch (Exception $e) {
            $taskInfo = $task->getInfo();
            if ($taskInfo->getAge() < (int) $helper->getRetryTimeout()) {
                $task->retry();
                $helper->log("EmailSender Worker didn't execute Indexer successfully; a further attempt was scheduled");
            } else {
                $task->hold();
                $helper->log("EmailSender Worker aborted Indexer execution dued to following exception: '%s'", $e->getMessage());
            }
        }
    }
}