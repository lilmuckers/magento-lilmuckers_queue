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
 * The queue adaptor abstract
 *
 * @category   Lilmuckers
 * @package    Lilmuckers_Queue
 * @author     Patrick McKinley <contact@patrick-mckinley.com>
 */
abstract class Lilmuckers_Queue_Model_Adapter_Abstract extends Varien_Object
{
    /**
     * Add a task to the queue
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function addTask($queue, Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //ensure the queue connection is loaded
        $this->_loadConnection();
        
        //queue this stuff up
        $this->_addToQueue($queue, $task);
        
        return $this;
    }
    
    /**
     * Load the connection handler
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    abstract protected function _loadConnection();
    
    /**
     * Add the task to the queue
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    abstract protected function _addToQueue($queue, Lilmuckers_Queue_Model_Queue_Task $task);
    
    /**
     * Get the task object for the queue in question
     * 
     * @param string $queue
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    abstract protected function _reserveFromQueue($queue);
    
    /**
     * Get the next task from the provided queue or array of queues
     * 
     * @param mixed $queue
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    public function getTask($queue)
    {
        if (is_array($queue)) {
            //retrieve and reserve the next job from a list of queues
            return $this->_reserveFromQueues($queue);
        } else {
            return $this->_reserveFromQueue($queue);
        }
    }
    
    /**
     * Get the next task object for the queues in question
     * 
     * @param array $queue
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    abstract protected function _reserveFromQueues($queues);
    
    /**
     * Touch the task to keep it reserved
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function touch(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_touch($task);
        return $this;
    }
    
    /**
     * Touch the task to keep it reserved
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    abstract protected function _touch(Lilmuckers_Queue_Model_Queue_Task $task);
    
    /**
     * Remove a task from the queue
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function remove(Lilmuckers_Queue_Model_Queue_Task $queue, Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_remove($queue, $task);
        return $this;
    }
    
    /**
     * Remove a task from the queue abstract method
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    abstract protected function _remove(Lilmuckers_Queue_Model_Queue_Task $queue, Lilmuckers_Queue_Model_Queue_Task $task);
    
    /**
     * Hold a task in the queue
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function hold(Lilmuckers_Queue_Model_Queue_Task $queue, Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_hold($queue, $task);
        return $this;
    }
    
    /**
     * Hold a task in the queue abstract method
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    abstract protected function _hold(Lilmuckers_Queue_Model_Queue_Task $queue, Lilmuckers_Queue_Model_Queue_Task $task);
    
    /**
     * Unhold a task in the queue 
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function unhold(Lilmuckers_Queue_Model_Queue_Task $queue, Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_unhold($queue, $task);
        return $this;
    }
    
    /**
     * Unhold a task in the queue - abstract method
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    abstract protected function _unhold(Lilmuckers_Queue_Model_Queue_Task $queue, Lilmuckers_Queue_Model_Queue_Task $task);
    
    /**
     * Requeue a task 
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function retry(Lilmuckers_Queue_Model_Queue_Task $queue, Lilmuckers_Queue_Model_Queue_Task $task)
    {
        $this->_retry($queue, $task);
        return $this;
    }
    
    /**
     * Requeue a task - abstract method
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    abstract protected function _retry(Lilmuckers_Queue_Model_Queue_Task $queue, Lilmuckers_Queue_Model_Queue_Task $task);
    
    /**
     * Get the meta information for a given task
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task 
     * @return Varien_Object
     */
    public function getInformation(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //load an array of the data, pre-mapped
        $_data = $this->_getMappedTaskData($task);
        
        //import it into a Varien_Object and return it
        $_taskData = new Varien_Object($_data);
        return $_taskData;
    }
    
    /**
     * Get the job meta information, mapped to the standard fields of:
     * 
     * queue => The queue code
     * state => The current task state
     * priority => The current priority
     * age => How long it's been in the system (seconds)
     * delay => How long it's execution offset is (seconds)
     * ttr => The TTR for the task
     * expiration => If the job is reserved - how long before it's returned to the queue
     * reserves => The number of times this has been reserved
     * timeouts => The number of times the task has timed out and been returned ot the queue
     * releases => The number of times the task has been manually returned to the queue
     * holds => The number of times the task has been held
     * unholds => The number of times the task has been unheld
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return array
     */
    abstract protected function _getMappedTaskData(Lilmuckers_Queue_Model_Queue_Task $task);
}