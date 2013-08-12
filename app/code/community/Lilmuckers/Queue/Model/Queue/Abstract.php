<?php
/**
 * Magento Simple Asyncronous Queuing Module
 *
 * @category    Lilmuckers
 * @package     Lilmuckers_Shell
 * @copyright   Copyright (c) 2013 Patrick McKinley (http://www.patrick-mckinley.com)
 * @license     http://choosealicense.com/licenses/mit/
 */

/**
 * Queue model handler abstract
 *
 * @category   Lilmuckers
 * @package    Lilmuckers_Queue
 * @author     Patrick McKinley <contact@patrick-mckinley.com>
 */
abstract class Lilmuckers_Queue_Model_Queue_Abstract extends Varien_Object
{
    /**
     * The assigned queue to work with
     * 
     * @var string
     */
    protected $_queue;
    
    /**
     * Initialise the queue with the queue name 
     * 
     * @param string $queue
     * @param string $context
     * @return void
     */
    public function __construct($queue)
    {
        //assign the queue name
        $this->_queue = $queue;
        
        //and run the inheritor
        parent::__construct();
    }
    
    /**
     * Set a new queue name
     * 
     * @param string $queue
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function setName($queue)
    {
        $this->_queue = $queue;
        return $this;
    }
    
    /**
     * Get the queue name
     * 
     * @return string
     */
    public function getName()
    {
        return $this->_queue;
    }
    
    /**
     * Set this queue to be in the worker context
     * 
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function setIsWorker()
    {
        return $this->_init();
    }
    
    /**
     * Initialise the queue for workers
     * 
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    protected function _init()
    {
        return $this;
    }
    
    /**
     * Add a task to the queue
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $data
     * @param int $priority
     * @param int $delay
     * @param int $ttr
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function addTask(Lilmuckers_Queue_Model_Queue_Task $task, $priority = null, $delay = null, $ttr = null)
    {
        //send the task data to the 
        $this->_getAdapter()->addTask($this->_queue, $task, $priority, $delay, $ttr);
        
        return $this;
    }
    
    /**
     * Get the queue backend handler
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    protected function _getAdapter()
    {
        return Mage::helper('lilqueue')->getAdapter();
    }
    
    /**
     * Get and reserve the next task for the queue
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    public function reserveNextTask()
    {
        //retrieve the task data from the queue, 
        //assign it the queue context and return
        $_task = $this->_getAdapter()->getTask($this->_queue);
        $_task->setQueue($this);
        
        return $_task;
    }
    
    /**
     * Run the next task for the given queue
     * 
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function runNextTask()
    {
        try{
            //reserve the task for me
            $_task = $this->reserveNextTask();
            
            //run the task and get the result status
            switch($_task->run())
            {
                case Lilmuckers_Queue_Model_Queue_Task::TASK_CONFIG_ERROR:
                case Lilmuckers_Queue_Model_Queue_Task::TASK_ERROR:
                case Lilmuckers_Queue_Model_Queue_Task::TASK_HOLD:
                    //if it's a configuration error - bury it
                    $this->hold($_task);
                    break;
                
                case Lilmuckers_Queue_Model_Queue_Task::TASK_RETRY:
                    //this has been flagged for a retry
                    $this->retry($_task);
                    break;
                
                case Lilmuckers_Queue_Model_Queue_Task::TASK_SUCCESS:
                default:
                    //for success and default behavour - remove from queue
                    $this->remove($_task);
                    break;
            }
        } catch(Lilmuckers_Queue_Model_Adapter_Timeout_Exception $e){
            //timeout waiting for job, ignore.
        }
        
        return $this;
    }
    
    /**
     * Remove a task from the queue
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function remove(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_getAdapter()
            ->remove($this, $task);
        
        return $this;
    }
    
    /**
     * Put the provided task on hold
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function hold(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_getAdapter()
            ->hold($this, $task);
        
        return $this;
    }
    
    /**
     * Put the provided held task back in the queue
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function unhold(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_getAdapter()
            ->unhold($this, $task);
        
        return $this;
    }
    
    /**
     * Put the provided task back in the queue
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function retry(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_getAdapter()
            ->retry($this, $task);
        
        return $this;
    }
}