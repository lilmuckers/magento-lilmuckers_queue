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
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Model_Worker extends Lilmuckers_Queue_Model_Worker_Abstract
{
    /** 
     * Test worker - just throws an exception
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task Task handler object 
     * 
     * @throws Mage_Core_Exception at all times
     * 
     * @return Lilmuckers_Queue_Model_Worker
     */
    public function testError(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        throw new Mage_Core_Exception('Test Exception');
        return $this;
    }
    
    /** 
     * Test worker - just flags the task as a success and ends
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task Task handler object
     * 
     * @return Lilmuckers_Queue_Model_Worker
     */
    public function testSuccess(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $task->success();
        return $this;
    }
    
    /** 
     * Test worker - just flags the task to be retried ends
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task Task handler object
     * 
     * @return Lilmuckers_Queue_Model_Worker
     */
    public function testRetry(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $task->retry();
        return $this;
    }
    
    /** 
     * Test worker - just flags the task as a success and ends
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task Task handler object
     * 
     * @return Lilmuckers_Queue_Model_Worker
     */
    public function test(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $task->success();
        return $this;
    }
}
