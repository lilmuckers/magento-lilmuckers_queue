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
 * The queue default helper
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * The config path to the backend type configuration
     */
    const QUEUE_BACKEND_CONFIG = 'global/queue/backend';
    
    /**
     * The generic path for the backend adapter config
     */
    const QUEUE_BACKEND_TYPE = 'global/queue/adapters/%s/class';
    
    /**
     * The generic path for the queue handler config
     */
    const QUEUE_HANDLER_TYPE = 'queues/%s/class';
    
    /**
     * The default queue handler model
     */
    const QUEUE_HANDLER_TYPE_DEFAULT = 'lilqueue/queue';
    
    /**
     * The location of the queue information within the config tree
     */
    const QUEUE_INFO = 'queues';
    
    /**
     * The default task handler
     */
    const TASK_HANDLER = 'lilqueue/queue_task';
    
    /**
     * The queue handler cache
     * 
     * @var array
     */
    protected $_queues = array();

    /**
     * The queue backend adapter
     * 
     * @var Lilmuckers_Queue_Model_Adapter_Abstract
     */
    protected $_adapter;
    
    /**
     * Get the queue system backend handler
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function getAdapter()
    {
        if (!$this->_adapter) {
            //get the queue backend type
            $_backend = (string) Mage::getConfig()
                ->getNode(self::QUEUE_BACKEND_CONFIG);
            
            //get an instance of the backend adapter
            $this->_adapter = $this->_getBackendAdapter($_backend);
        }
        
        return $this->_adapter;
    }
    
    /**
     * Load an instance of the supplied backend adapter
     * 
     * @param string $backend The backend code from the config.xml
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    protected function _getBackendAdapter($backend)
    {
        //get the backend model type
        $_configPath       = sprintf(self::QUEUE_BACKEND_TYPE, $backend);
        $_backendModelType = (string) Mage::getConfig()->getNode($_configPath);
        
        //Instantiate the model
        $_backendModel = Mage::getSingleton($_backendModelType);
        
        return $_backendModel;
    }
    
    /**
     * Get the queue handler for the given queue
     * 
     * @param string $queue  The code for the queue handler
     * @param bool   $worker Flag if this queue is to be used to run workers
     * 
     * @return Lilmuckers_Queue_Model_Queue_Abstract
     */
    public function getQueue($queue, $worker = false)
    {
        //check the cache
        if (!array_key_exists($queue, $this->_queues)) {
            //get the model path
            $_configPath       = sprintf(self::QUEUE_HANDLER_TYPE, $queue);
            $_queueHandlerType = (string) Mage::getConfig()
                ->getNode($_configPath);
            
            //default it if not set
            if (!$_queueHandlerType) {
                $_queueHandlerType = self::QUEUE_HANDLER_TYPE_DEFAULT;
            }
            
            //instantiate the handler
            $this->_queues[$queue] = Mage::getModel(
                $_queueHandlerType, 
                array('queue'=>$queue)
            );
            
            //check the context
            if ($worker) {
                $this->_queues[$queue]->setIsWorker();
            }
        }
        
        //return the cached version
        return $this->_queues[$queue];
    }
    
    /**
     * Helper for creating a task
     * 
     * @param string $task  The code for the task to run
     * @param array  $data  The data array to pass to the task
     * @param mixed  $store The store context to run the task with
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    public function createTask($task, $data = array(), $store = null)
    {
        //instantiate the task model
        $_task = Mage::getModel(self::TASK_HANDLER);
        
        //set the task, data and context
        $_task->setTask($task)
            ->setStore($store)
            ->setData($data);
        
        return $_task;
    }
    
    /**
     * Get all the information about all queues
     * 
     * @return array
     */
    public function getQueues()
    {
        //load all the queues
        $_queues = Mage::getConfig()->getNode(self::QUEUE_INFO);
        
        return $_queues->asArray();
    }
}
