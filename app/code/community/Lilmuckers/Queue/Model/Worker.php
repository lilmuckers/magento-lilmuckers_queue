<?php 
/**
 * Magento Simple Asyncronous Queuing Module
 *
 * @category    Lilmuckers
 * @package     Lilmuckers_Queue
 * @copyright   Copyright (c) 2013 Patrick McKinley (http://www.patrick-mckinley.com)
 * @license     http://choosealicense.com/licenses/mit/
 */

/**
 * Worker class
 *
 * @category   Lilmuckers
 * @package    Lilmuckers_Queue
 * @author     Patrick McKinley <contact@patrick-mckinley.com>
 */
class Lilmuckers_Queue_Model_Worker extends Lilmuckers_Queue_Model_Worker_Abstract
{
    /** 
     * Test worker - just flags the task as a success and ends
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Worker
     */
    public function test($task)
    {
        $task->success();
        return $this;
    }
}