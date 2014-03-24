<?php
/**
 * Magento Simple Asyncronous Queuing Module
 *
 * @category  Lilmuckers
 * @package   Lilmuckers_Queue
 * @copyright Copyright (c) 2013 Patrick McKinley (http://www.patrick-mckinley.com)
 * @license   http://choosealicense.com/licenses/mit/
 */

/**
 * Queue model handler abstract
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
abstract class Lilmuckers_Queue_Model_Queue_Abstract extends Varien_Object
{
    /**
     * The queue identifier for this queue
     */
    const DEFAULT_QUEUE_CODE = 'default';

    /**
     * The assigned queue to work with
     * 
     * @var string
     */
    protected $_queue;

    /**
     * Initialise the queue with the queue name 
     * 
     * @param mixed $queue The queue identifier
     * 
     * @return void
     */
    public function __construct($queue = null)
    {
        //set the queue appropriately
        if (is_null($queue) || !array_key_exists('queue', $queue)) {
            $_queue = self::DEFAULT_QUEUE_CODE;
        } else {
            $_queue = $queue['queue'];
        }
        
        //assign the queue name
        $this->_queue = $_queue;
        
        //and run the inheritor
        parent::__construct();
    }
    
    /**
     * Set a new queue name
     * 
     * @param string $queue The queue identifier
     * 
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
     * @param Lilmuckers_Queue_Model_Queue_Task $task The task handler
     * 
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function addTask(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //send the task data to the 
        $this->_getAdapter()->addTask($this, $task);
        
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
     * Get but don't reserve the next task for the queue
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    public function getNextUnreservedTask()
    {
        //retrieve the task data from the queue, 
        //assign it the queue context and return
        $_task = $this->_getAdapter()->getUnreservedTask($this);
        $_task->setQueue($this);
        
        return $_task;
    }
    
    /**
     * Get but don't reserve the next task for the queue
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    public function getNextUnreservedDelayedTask()
    {
        //retrieve the task data from the queue, 
        //assign it the queue context and return
        $_task = $this->_getAdapter()->getUnreservedDelayedTask($this);
        $_task->setQueue($this);
        
        return $_task;
    }
    
    /**
     * Get but don't reserve the next task for the queue
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    public function getNextUnreservedHeldTask()
    {
        //retrieve the task data from the queue, 
        //assign it the queue context and return
        $_task = $this->_getAdapter()->getUnreservedHeldTask($this);
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
            return $this->runTask($_task);
            
        } catch(Lilmuckers_Queue_Model_Adapter_Timeout_Exception $e){
            //timeout waiting for job, ignore.
        }
    }
    
    /**
     * Run the specific task
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task The task to run
     * 
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     * 
     * @throws Lilmuckers_Queue_Model_Queue_Db_Exception
     */
    public function runTask(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //run the task and get the result status
        switch($task->run())
        {
            case Lilmuckers_Queue_Model_Queue_Task::TASK_CONFIG_ERROR:
            case Lilmuckers_Queue_Model_Queue_Task::TASK_ERROR:
            case Lilmuckers_Queue_Model_Queue_Task::TASK_HOLD:
                //if it's a configuration error - bury it
                $this->hold($task);
                break;
            
            case Lilmuckers_Queue_Model_Queue_Task::TASK_DATABASE_ERROR
                //flag this for a retry, and then throw a DB error
                //for the queue watcher script to catch
                $this->retry($task);
                throw new Lilmuckers_Queue_Model_Queue_Db_Exception(
                    'Unspecified database connection error'
                );
                break;
                
            case Lilmuckers_Queue_Model_Queue_Task::TASK_RETRY:
                //this has been flagged for a retry
                $this->retry($task);
                break;
                
            case Lilmuckers_Queue_Model_Queue_Task::TASK_SUCCESS:
            default:
                //for success and default behavour - remove from queue
                $this->remove($task);
                break;
        }
        
        return $this;
    }
    
    /**
     * Remove a task from the queue
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task Task to remove from queue
     * 
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
     * @param Lilmuckers_Queue_Model_Queue_Task $task Task to put on hold
     * 
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
     * @param Lilmuckers_Queue_Model_Queue_Task $task The task to unhold
     * 
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function unhold(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_getAdapter()
            ->unhold($this, $task);
        
        return $this;
    }
    
    /**
     * Unhold multiple tasks at once in this queue
     * 
     * @param int $number The number of tasks to kick
     * 
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function unholdMultiple($number)
    {
        $this->_getAdapter()
            ->unholdMultiple($number, $this);
        return $this;
    }
    
    /**
     * Put the provided task back in the queue
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task The task to retry
     * 
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function retry(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_getAdapter()
            ->retry($this, $task);
        
        return $this;
    }
}
