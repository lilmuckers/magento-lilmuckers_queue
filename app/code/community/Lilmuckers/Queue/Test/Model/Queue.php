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
 * Tests for the Queue handler
 * 
 * Performs the following tests
 * 1.  Adding a task to queue and get it unreserved from the queue
 * 2.  Reserve a task from the queue
 * 3.  Check we can get a delayed task
 * 4.  Check we can get a held task
 * 5.  Check we can run the next task
 * 8.  Check we can run a task that gets removed
 * 6.  Check we can run a task that gets held
 * 7.  Check we can run a task that gets retried
 * 9.  Check we can remove a task from the queue
 *  -- 10. Check we can unhold a task -- disabled because this isn't fully supported
 * 11. Check we unhold multiple tasks
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Test_Model_Queue extends EcomDev_PHPUnit_Test_Case
{
    /**
     * The default task data
     */
    const DEFAULT_TASK_DATA = '12357890';
        
    /**
     * Beanstalk object used for the test
     *
     * @var Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected $_adapter;
    
    /**
     * Queue object used for the test
     *
     * @var Lilmuckers_Queue_Model_Queue
     */
    protected $_queue;
    
    /**
     * helper object used for the test
     *
     * @var Lilmuckers_Queue_Helper_Data
     */
    protected $_helper;

    /**
     * Setup function, initialise the helper object
     *
     * @return void
     */
    protected function setUp()
    {
        $this->_helper  = Mage::helper('lilqueue');
        $this->_adapter = $this->_helper->getAdapter();
        $this->_queue   = $this->_helper->getQueue('unit_tests');
    }
    
    /**
     * Create a task for testing with
     * 
     * @param int    $randomFactor A random factor to add to the task data
     * @param string $task         The task to flag
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _getTask($randomFactor = self::DEFAULT_TASK_DATA, $task = 'test')
    {
        return $this->_helper->createTask($task, array('test'=>$randomFactor));
    }
    
    /**
     * Check that a task can be added to the queue and peeked at correctly
     * 
     * @return void
     * 
     * @test
     */
    public function testAddTask()
    {
        //get the task
        $_task = $this->_getTask();
        
        //add and then retrieve the task
        $this->_queue->addTask($_task);
        $_task2 = $this->_queue->getNextUnreservedTask();
        
        //check they're the same data - this is not infallible, as there could be an
        //extant job in the queue that matches, but i can't think of a better way to
        //check this
        $this->assertEquals(
            $_task->getTask(),
            $_task2->getTask(),
            'Check that the task is in the queue'
        );
        
        //make sure the data set was the same
        $this->assertEquals(
            $_task->getTest(),
            $_task2->getTest(),
            'Check that tasks have the same data'
        );
        
        //make sure that this task is still queued
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::QUEUED,
            $_task2->getInfo(true)->getState(),
            'Check that the unreserved task is, infact, unreserved'
        );
        
        //delete the task that was set
        $this->_queue->remove($_task2);
    }
    
    /**
     * Check that a task can be added to the queue and reserved correctly
     * 
     * @return void
     * 
     * @test
     */
    public function testReserveTask()
    {
        //get the task
        $_task = $this->_getTask();
        
        //add and then retrieve the task
        $this->_queue->addTask($_task);
        $_task2 = $this->_queue->reserveNextTask();
        
        //check they're the same data - this is not infallible, as there could be an
        //extant job in the queue that matches, but i can't think of a better way to
        //check this
        $this->assertEquals(
            $_task->getTask(),
            $_task2->getTask(),
            'Check that the task is in the queue'
        );
        
        //make sure the data set was the same
        $this->assertEquals(
            $_task->getTest(),
            $_task2->getTest(),
            'Check that tasks have the same data'
        );
        
        //make sure that this task is reserved
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::RESERVED,
            $_task2->getInfo(true)->getState(),
            'Check that the reserved task is reserved'
        );
        
        //delete the task that was set
        $this->_queue->remove($_task2);
    }
    
    /**
     * Check that a delayed task can be added to the queue correctly and peeked at
     * 
     * @return void
     * 
     * @test
     */
    public function testGetDelayedTask()
    {
        //get the task
        $_task = $this->_getTask();
        $_task->setDelay(5);
        
        //add and then retrieve the task
        $this->_queue->addTask($_task);
        $_task2 = $this->_queue->getNextUnreservedDelayedTask();
        
        //check they're the same data - this is not infallible, as there could be an
        //extant job in the queue that matches, but i can't think of a better way to
        //check this
        $this->assertEquals(
            $_task->getTask(),
            $_task2->getTask(),
            'Check that the task is in the queue'
        );
        
        //make sure the data set was the same
        $this->assertEquals(
            $_task->getTest(),
            $_task2->getTest(),
            'Check that tasks have the same data'
        );
        
        //make sure that this task is reserved
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::DELAYED,
            $_task2->getInfo(true)->getState(),
            'Check that the reserved task is delayed'
        );
        
        //kick the delayed tasks
        $this->_adapter->unholdMultiple(10);
        
        //delete the task that was set
        $this->_queue->remove($_task2);
    }
    
    /**
     * Check that a task can be pulled from the queue and held
     * and that it can be unheld correctly
     * 
     * @return void
     * 
     * @test
     */
    public function testGetHeldTask()
    {
        //get the task
        $_task = $this->_getTask();
        
        //add and then retrieve the task
        $this->_queue->addTask($_task);
        $_task2 = $this->_queue->reserveNextTask();
        
        //put task on hold
        $this->_queue->hold($_task2);
        
        //check that it's on hold
        //make sure that this task is reserved
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::HELD,
            $_task2->getInfo(true)->getState(),
            'Check that the reserved task is held'
        );
        
        //kick the held tasks
        $this->_adapter->unholdMultiple(10);
        
        //check that it's back to being queued
        //make sure that this task is reserved
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::QUEUED,
            $_task2->getInfo(true)->getState(),
            'Check that the reserved task is held'
        );
        
        //delete the task that was set
        $this->_queue->remove($_task2);
    }
    
    /**
     * Check that a task can be pulled from the queue and removed automatically
     * 
     * @return void
     * 
     * @test
     */
    public function testRunTask()
    {
        //check that the success runs alright
        $_task = $this->_getTask(
            self::DEFAULT_TASK_DATA, 
            'test_success'
        );
        
        //add and then retrieve the task
        $this->_queue->addTask($_task);
        
        //load the task - so we can inspect it later
        $_task2 = $this->_queue->getNextUnreservedTask();
        
        //run the worker handler
        $this->_queue->runNextTask();
        
        //check that the task was removed
        $this->assertEquals(
            array(),
            $_task2->getInfo(true)->getData(),
            'Check that there is no data'
        );
    }
    
    /**
     * Check that a task can be pulled from the queue and held
     * and that it can be unheld correctly
     * 
     * @return void
     * 
     * @test
     */
    public function testHoldTask()
    {
        //check that the success runs alright
        $_task = $this->_getTask(
            self::DEFAULT_TASK_DATA, 
            'test_error'
        );
        
        //add and then retrieve the task
        $this->_queue->addTask($_task);
        
        //load the task - so we can inspect it later
        $_task2 = $this->_queue->getNextUnreservedTask();
        
        //run the worker handler
        $this->_queue->runNextTask();
        
        //check that the task was held
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::HELD,
            $_task2->getInfo(true)->getState(),
            'Check that the task was held'
        );
        
        //delete the task that was set
        $this->_queue->remove($_task2);
    }
    
    /**
     * Check that a task can be pulled from the queue and flagged to retry
     * and that it can be unheld correctly
     * 
     * @return void
     * 
     * @test
     */
    public function testRetryTask()
    {
        //check that the success runs alright
        $_task = $this->_getTask(
            self::DEFAULT_TASK_DATA, 
            'test_retry'
        );
        
        //add and then retrieve the task
        $this->_queue->addTask($_task);
        
        //load the task - so we can inspect it later
        $_task2 = $this->_queue->getNextUnreservedTask();
        
        //run the worker handler
        $this->_queue->runNextTask();
        
        //check that the task was removed
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::QUEUED,
            $_task2->getInfo(true)->getState(),
            'Check that the task was held'
        );
        
        //delete the task that was set
        $this->_queue->remove($_task2);
    }
    
    /**
     * Check that a task can be pulled from the queue and flagged to retry
     * and that it can be unheld correctly
     * 
     * @return void
     * 
     * @test
     */
    public function testRemove()
    {
        //get the task
        $_task = $this->_getTask();
        
        //add and then retrieve the task
        $this->_queue->addTask($_task);
        $_task2 = $this->_queue->reserveNextTask();
        
        //delete the task that was set
        $this->_queue->remove($_task2);
        
        //check that the task was removed
        $this->assertEquals(
            array(),
            $_task2->getInfo(true)->getData(),
            'Check that there is no data'
        );
    }
    
    /**
     * Check that we can queue multiple tasks, hold them, and then unhold them all
     * 
     * @return void
     * 
     * @test
     */
    public function testMultipleUnhold()
    {
        //number of tasks to work with
        $_taskCount = 5;
        $_tasks     = array();
        
        //create and queue the tasks
        for ($i = 0; $i < $_taskCount; $i++) {
            //add and then retrieve the task
            $this->_queue->addTask(
                $this->_getTask(
                    self::DEFAULT_TASK_DATA, 
                    'test_error'
                )
            );
            
            $_tasks[$i] = $this->_queue->getNextUnreservedTask();
            
            //run the tasks
            $this->_queue->runNextTask();
            
            //check it's been held
            $this->assertEquals(
                Lilmuckers_Queue_Model_Queue_Task_State::HELD,
                $_tasks[$i]->getInfo(true)->getState(),
                'Check that the task was held'
            );
        }
        
        // multiple unholds
        $this->_adapter->unholdMultiple(10);
        
        //create and queue the tasks
        for ($i = 0; $i < $_taskCount; $i++) {
            //check it's been held
            $this->assertEquals(
                Lilmuckers_Queue_Model_Queue_Task_State::QUEUED,
                $_tasks[$i]->getInfo(true)->getState(),
                'Check that the task was unheld'
            );
            
            //remove the task
            $this->_queue->remove($_tasks[$i]);
        }
    }
}
