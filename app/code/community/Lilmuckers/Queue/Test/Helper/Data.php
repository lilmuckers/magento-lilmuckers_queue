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
 * Tests for the default helper
 * 
 * Test being performed
 * 1. Check we can load a backend adapter
 * 2. Check we can load a queue handler
 * 3. Check we can load a task handler
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Test_Helper_Data extends EcomDev_PHPUnit_Test_Case
{
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
        $this->_helper = Mage::helper('lilqueue');
    }
    
    /**
     * Test to load the backend adapter
     * 
     * @return void
     * 
     * @test
     */
    public function testLoadingBackendAdapter()
    {
        //get the backend adapter
        $_adapter = $this->_helper->getAdapter();
        
        //assert that it's an instance of the adapter abstract
        $this->assertInstanceOf(
            'Lilmuckers_Queue_Model_Adapter_Abstract', 
            $_adapter,
            'Check that a valid adapter is loaded'
        );
    }
    
    /**
     * Check that a task can be added to the queue correctly
     * 
     * @return void
     * 
     * @test
     */
    public function testLoadQueue()
    {
        //check that the queue object is correct
        $_queue = $this->_helper->getQueue('default');
        $this->assertInstanceOf(
            'Lilmuckers_Queue_Model_Queue',
            $_queue,
            'Check that the queue object is instantiated'
        );
        
        //ensure the name on the queue is correct
        $this->assertEquals(
            'default',
            $_queue->getName(),
            'Check that the queue name was set to \'default\''
        );
    }
    
    /**
     * Test loading of the task
     * 
     * @return void
     * 
     * @test
     */
    public function testLoadTask()
    {
        //generate a random value to verify
        $_testValue = mt_rand();
        
        //create a task and check it's instantiated
        $_task = $this->_helper->createTask('test', array('test'=>$_testValue));
        $this->assertInstanceOf(
            'Lilmuckers_Queue_Model_Queue_Task',
            $_task,
            'Check that the task object was instantiated'
        );
        
        //ensure the store ID on the task is correct
        $this->assertEquals(
            $_task->getStore()->getId(),
            Mage::app()->getStore()->getId(),
            'Check that the store view id is set to the task'
        );
        
        //ensure the data that was set is okay
        $this->assertEquals(
            $_testValue,
            $_task->getTest()
        );
    }
}
