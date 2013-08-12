<?php

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
     * Get the next task from the queue
     * 
     * @param string $queue
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    public function getTask($queue)
    {
        //retrieve and reserve the next job
        return $this->_reserveFromQueue($queue);
    }
    
    /**
     * Get the JSON string for the task in question
     * 
     * @param string $queue
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    abstract protected function _reserveFromQueue($queue);
    
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
    public function remove($queue, Lilmuckers_Queue_Model_Queue_Task $task)
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
    abstract protected function _remove($queue, Lilmuckers_Queue_Model_Queue_Task $task);
    
    /**
     * Hold a task in the queue
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function hold($queue, Lilmuckers_Queue_Model_Queue_Task $task)
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
    abstract protected function _hold($queue, Lilmuckers_Queue_Model_Queue_Task $task);
    
    /**
     * Unhold a task in the queue 
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function unhold($queue, Lilmuckers_Queue_Model_Queue_Task $task)
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
    abstract protected function _unhold($queue, Lilmuckers_Queue_Model_Queue_Task $task);
    
    /**
     * Requeue a task 
     * 
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function retry($queue, Lilmuckers_Queue_Model_Queue_Task $task)
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
    abstract protected function _retry($queue, Lilmuckers_Queue_Model_Queue_Task $task);
}