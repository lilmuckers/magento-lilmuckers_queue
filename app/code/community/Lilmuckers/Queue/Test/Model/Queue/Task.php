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
 * Tests for the Queue Task handler
 * 
 * Tests being performed
 * 1. Test the getters and setters work correctly
 * 2. Test that the status-setters work correctly
 * 3. Check that the queue is pulled correctly
 * 4. Make sure that the info-pull works
 * 5. Check that the data exports as JSON correctly
 * 6. Check that the data imports correctly
 * 7. Check that we can run a task correctly
 * 8. Check that we can run a task that throws an error and detect it
 * 9. Check that we get the right error code for a misconfigured task
 * 10. Check that we can touch a task
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Test_Model_Queue_Task extends EcomDev_PHPUnit_Test_Case
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
     * Check we can use the getters and setters appropriately
     * 
     * @return void
     * 
     * @test
     */
    public function testGettersSetters()
    {
        //Get a task
        $_task = $this->_getTask();
        
        //test priority
        $_priority = 123;
        $_task->setPriority($_priority);
        $this->assertEquals(
            $_priority,
            $_task->getPriority(),
            'Check that priority was set correctly'
        );
        
        //test ttr
        $_ttr = 62;
        $_task->setTtr($_ttr);
        $this->assertEquals(
            $_ttr,
            $_task->getTtr(),
            'Check that ttr was set correctly'
        );
        
        //test delay
        $_delay = 10;
        $_task->setDelay($_delay);
        $this->assertEquals(
            $_delay,
            $_task->getDelay(),
            'Check that delay was set correctly'
        );
        
        //test status
        $_status = 'testing';
        $_task->setStatus($_status);
        $this->assertEquals(
            $_status,
            $_task->getStatus(),
            'Check that status was set correctly'
        );
        
        //set the task to the queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //peek at queue task
        $_task2 = $this->_adapter->getUnreservedDelayedTask($this->_queue);
        
        //check that the data is as expected
        $this->assertEquals(
            array(
              'queue'      => 'unit_tests',
              'state'      => 'delayed',
              'priority'   => (string) $_priority,
              'age'        => '0',
              'delay'      => (string) $_delay,
              'ttr'        => (string) $_ttr,
              'expiration' => (string) ($_delay - 1),
              'reserves'   => '0',
              'timeouts'   => '0',
              'releases'   => '0',
              'holds'      => '0',
              'unholds'    => '0'
            ),
            $this->_adapter->getInformation($_task2)->getData(),
            'Check that the task information is as we expected'
        );
        
        //kick the delayed tasks
        $this->_adapter->unholdMultiple(10);
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Test that the specific statuses are being set
     * 
     * @return void
     * 
     * @test
     */
    public function testSpecificStatuses()
    {
        //Get a task
        $_task = $this->_getTask();
    
        //test the specific status methods
        //retry
        $_task->retry();
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task::TASK_RETRY,
            $_task->getStatus(),
            'Check that status was set to \'retry\' correctly'
        );
        
        //success
        $_task->success();
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task::TASK_SUCCESS,
            $_task->getStatus(),
            'Check that status was set to \'success\' correctly'
        );
        
        //hold
        $_task->hold();
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task::TASK_HOLD,
            $_task->getStatus(),
            'Check that status was set to \'hold\' correctly'
        );
    }
    
    /**
     * Check we can use the getters and setters appropriately
     * 
     * @return void
     * 
     * @test
     */
    public function testQueueLoad()
    {
        //Get a task
        $_task = $this->_getTask();
        
        //set the task to the queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //reserve a task outside of a specific queue
        // When an array is passed to this function it does not assume the
        // queue context of the task, and the queue will use the info call
        // to work out its appropriate queue context
        $_task2 = $this->_adapter->getTask(array($this->_queue->getName()));
        
        //get the queue
        $_queue = $_task->getQueue();
        $this->assertInstanceOf(
            'Lilmuckers_Queue_Model_Queue_Abstract', 
            $_queue,
            'Check that a valid queue handler is loaded'
        );
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Check we can get the task information
     * 
     * @return void
     * 
     * @test
     */
    public function testGetInfo()
    {
        //Get a task
        $_task = $this->_getTask();
        
        //set the task to the queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //reserve a queue task
        $_task2 = $this->_adapter->getTask($this->_queue->getName());
        
        //check that the data is as expected
        $this->assertEquals(
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
            $_task2->getInfo(true)->getData(),
            'Check that the task information is as we expected'
        );
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Can we export the data correctly
     * 
     * @return void
     * 
     * @test
     */
    public function testDataExport()
    {
        //Get a task
        $_task = $this->_getTask();
        $_json = $_task->exportData();
        
        $this->assertEquals(
            '{"task":"test","store":"0","data":'
            .'{"test":"'.self::DEFAULT_TASK_DATA.'"}}',
            $_json,
            'Check that the data exports cleanly'
        );
    }
    
    /**
     * Can we import the data correctly
     * 
     * @return void
     * 
     * @test
     */
    public function testDataImport()
    {
        //Get an empty task
        $_task       = Mage::getModel(Lilmuckers_Queue_Helper_Data::TASK_HANDLER);
        $_taskToTest = 'testTask';
        $_testImport = '{"task":"'.$_taskToTest.'","store":"0",'
            .'"data":{"test":"'.self::DEFAULT_TASK_DATA.'"}}';
        
        $_task->importData($_testImport);
        
        $this->assertEquals(
            $_taskToTest,
            $_task->getTask(),
            'Check that the task imported cleanly'
        );
        
        $this->assertEquals(
            self::DEFAULT_TASK_DATA,
            $_task->getTest(),
            'Check that the task data imported cleanly'
        );
    }
    
    /**
     * Can we run the right worker and end on success
     * 
     * @return void
     * 
     * @test
     */
    public function testRunWorker()
    {
        //Get a task
        $_task = $this->_getTask(self::DEFAULT_TASK_DATA, 'test_success');
                
        //set the task to the queue
        $this->_adapter->addTask($this->_queue, $_task);

        //reserve a queue task
        $_task2 = $this->_adapter->getTask($this->_queue->getName());
        
        //run the worker
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task::TASK_SUCCESS,
            $_task2->run(),
            'Ensure that the job is marked as successful'
        );
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Can we run the right worker
     * 
     * @return void
     * 
     * @test
     */
    public function testRunWorkerError()
    {
        //Get a task
        $_task = $this->_getTask(self::DEFAULT_TASK_DATA, 'test_error');
                
        //set the task to the queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //reserve a queue task
        $_task2 = $this->_adapter->getTask($this->_queue->getName());
        
        //run the worker
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task::TASK_ERROR,
            $_task2->run(),
            'Ensure that an error is returned from running the task'
        );
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Can we run the right worker
     * 
     * @return void
     * 
     * @test
     */
    public function testRunWorkerFail()
    {
        //Get a task
        $_task = $this->_getTask(self::DEFAULT_TASK_DATA, 'test_invalid');
        
        //set the task to the queue
        $this->_adapter->addTask($this->_queue, $_task);
        
        //reserve a queue task
        $_task2 = $this->_adapter->getTask($this->_queue->getName());
        
        //run the worker
        $this->assertEquals(
            Lilmuckers_Queue_Model_Queue_Task::TASK_CONFIG_ERROR,
            $_task2->run(),
            'Ensure that a config error is returned from running the task'
        );
        
        //delete the task that was set
        $this->_adapter->remove($this->_queue, $_task2);
    }
    
    /**
     * Can we extend the expiration time for the task
     * 
     * @return void
     * 
     * @test
     */
    public function testTouch()
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
        $_task2->touch();
        
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
}
