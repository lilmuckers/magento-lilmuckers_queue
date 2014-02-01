<?php

/**
 * NicheCommerce
 *
 * @category    NicheCommerce
 * @package     NicheCommerce
 * @copyright   Copyright (c) 2014 NicheCommerce. (http://nichecommerce.nl)
 * @author      Tim Vroom (tim@nichecommerce.nl)
 */
class Lilmuckers_Queue_Model_Indexer_Worker extends Lilmuckers_Queue_Model_Worker_Abstract
{
    /**
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     *
     * @return int
     */
    public function run($task)
    {
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

            $task->setStatus(Lilmuckers_Queue_Model_Queue_Task::TASK_SUCCESS);
        } catch (Exception $e) {
            $task->setStatus(Lilmuckers_Queue_Model_Queue_Task::TASK_RETRY);
        }
    }
}