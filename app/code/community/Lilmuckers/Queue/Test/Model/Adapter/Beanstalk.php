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
 * Tests for the Beanstalk adapter
 * 
 * Tests being performed
 * 1. Checks if the connection is instantiated
 * 2. Check we can add a task and then remove it
 * 3. Check that we can reserve the task
 * 4. Check we can re-reserve a task
 * 5. Check we can hold and unhold a task
 * 6. Check we can requeue a task
 * 7. Check we can get information on the task
 * 8. Check we can get an unreserved task
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Test_Model_Adapter_Beanstalk extends EcomDev_PHPUnit_Test_Case
{
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
     * @param int $randomFactor a random factor to add to the task data
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _getTask($randomFactor)
    {
        return $this->_helper->createTask('test', array('test'=>$randomFactor));
    }
    
    /**
     * Test that the connection is instantiated
     * 
     * @return void
     * 
     * @test
     */
    public function testConnection()
    {
        $_connection = $this->_adapter->getConnection();
        
        //check that it's a pheanstalk conection
        $this->assertInstanceOf(
            'Pheanstalk_Pheanstalk', 
            $_connection,
            'Check that a the beanstalk connection is loaded'
        );
        
        //check that we're actually connected
        $this->assertTrue(
            $_connection->getConnection()->isServiceListening(),
            'Check that we\'re connected to beanstalkd'
        );
    }
    
    /**
     * Check we can add a task to the queue and peek at it and remove the queue
     * 
     * @return void
     * 
     * @expectedException Pheanstalk_Exception_ServerException
     * @test
     */
    public function testAddTask()
    {
        //generate a random value to verify
        $_testValue = mt_rand();
        
        //create a task and check it's instantiated
        $_task = $this->_getTask($_testValue);
        
        //add task to queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //peek at queue task
        $_task2 = $this->_adapter->getUnreservedTask($this->_queue);
        
        //check it's the same task
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
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
        
        //peek at queue task - this should throw an exception
        $_task3 = $this->_adapter->getUnreservedTask($this->_queue);
    }
    
    /**
     * Test that we can reserve a task from the queue
     * 
     * @return void
     * 
     * @test
     */
    public function testReserveTask()
    {
        //generate a random value to verify
        $_testValue = mt_rand();
        
        //create a task and check it's instantiated
        $_task = $this->_getTask($_testValue);
        
        //add task to queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //reserve a queue task
        $_task2 = $this->_adapter->getTask($this->_queue->getName());
        
        //check it's the same task
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
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Test that we can touch a task with our greasy messy fingers
     * 
     * @return void
     * 
     * @test
     */
    public function testTouchTask()
    {
        //generate a random value to verify
        $_testValue = mt_rand();
        
        //create a task and check it's instantiated
        $_task = $this->_getTask($_testValue);
        
        //add task to queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //reserve a queue task
        $_task2 = $this->_adapter->getTask($this->_queue->getName());
        
        //sleep for 5 seconds to allow the time-left to tick down
        sleep(5);
        
        //get the time-left prior to touching it
        $_timeLeft1 = $_task2->getInfo(true)->getExpiration();
        
        //touch the task
        $this->_adapter->touch($_task2);
        
        //get the time-left post touch
        $_timeLeft2 = $_task2->getInfo(true)->getExpiration();
        
        //get the information for the task
        $this->assertGreaterThanOrEqual(
            $_timeLeft1,
            $_timeLeft2,
            'Check that the expiration has been updated'
        );
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Test that a task can be held and unheld
     * 
     * @return void
     * 
     * @test
     */
    public function testHoldTask()
    {
        //generate a random value to verify
        $_testValue = mt_rand();
        
        //create a task and check it's instantiated
        $_task = $this->_getTask($_testValue);
        
        //add task to queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //reserve a queue task
        $_task2 = $this->_adapter->getTask($this->_queue->getName());
        
        //put the task on hold
        $this->_adapter->hold($this->_queue, $_task2);
        
        //get the info on the task
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::HELD,
            $_task2->getInfo(true)->getState(),
            'Check that the task was held'
        );
        
        //unhold the task - this doesn't cover standard hold
        $this->_adapter->unholdMultiple(10, $this->_queue);
        
        //get the info on the task
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::QUEUED,
            $_task2->getInfo(true)->getState(),
            'Check that the task was requeued'
        );
                
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Test that tasks are requeued
     * 
     * @return void
     * 
     * @test
     */
    public function testRetryTask()
    {
        //generate a random value to verify
        $_testValue = mt_rand();
        
        //create a task and check it's instantiated
        $_task = $this->_getTask($_testValue);
        
        //add task to queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //reserve a queue task
        $_task2 = $this->_adapter->getTask($this->_queue->getName());
        
        //get the info on the task
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::RESERVED,
            $_task2->getInfo(true)->getState(),
            'Check that the task is reserved'
        );
        
        //retry the task
        $this->_adapter->retry($this->_queue, $_task2);
        
        //get the info on the task
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::QUEUED,
            $_task2->getInfo(true)->getState(),
            'Check that the task was requeued'
        );
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Check that we can get the information of a task
     * 
     * @return void
     * 
     * @test
     */
    public function testGetTaskInformation()
    {
        //generate a random value to verify
        $_testValue = mt_rand();
        
        //create a task and check it's instantiated
        $_task = $this->_getTask($_testValue);
        
        //add task to queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //reserve a queue task
        $_task2 = $this->_adapter->getTask($this->_queue->getName());
        
        //check that the data is as expected
        $this->assertEquals(
            $this->_adapter->getInformation($_task2)->getData(),
            array(
              'queue'      => 'unit_tests',
              'state'      => 'reserved',
              'priority'   => '1024',
              'age'        => '0',
              'delay'      => '0',
              'ttr'        => '60',
              'expiration' => '59',
              'reserves'   => '1',
              'timeouts'   => '0',
              'releases'   => '0',
              'holds'      => '0',
              'unholds'    => '0'
            ),
            'Check that the task information is as we expected'
        );
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Test that we can get an unreserved task
     * 
     * @return void
     * 
     * @test
     */
    public function testGetUnreservedTask()
    {
        //generate a random value to verify
        $_testValue = mt_rand();
        
        //create a task and check it's instantiated
        $_task = $this->_getTask($_testValue);
        
        //add task to queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //peek at queue task
        $_task2 = $this->_adapter->getUnreservedTask($this->_queue);
        
        //get the info on the task and check that it's still queued
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task_State::QUEUED,
            $_task2->getInfo(true)->getState(),
            'Check that the task was requeued'
        );
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
}
