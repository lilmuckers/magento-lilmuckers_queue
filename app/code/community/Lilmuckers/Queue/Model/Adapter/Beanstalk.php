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
 * The beanstalkd queue adaptor
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Model_Adapter_Beanstalk extends Lilmuckers_Queue_Model_Adapter_Abstract
{
    /**
     * Constant for the path to the beanstalkd config
     */
    const BEANSTALKD_CONFIG = 'global/queue/beanstalkd/servers/server';
    
    /**
     * Job status codes
     */
    const STATUS_READY    = 'ready';
    const STATUS_RESERVED = 'reserved';
    const STATUS_DELAYED  = 'delayed';
    const STATUS_BURIED   = 'buried';
    
    /**
     * The Pheanstalk object
     * 
     * @var Pheanstalk_Pheanstalk
     */
    protected $_pheanstalk;
    
    /**
     * The default task priority
     * 
     * @var int
     */
    protected $_priority;
    
    /**
     * The default task delay
     * 
     * @var int
     */
    protected $_delay;
    
    /**
     * The default task ttr
     * 
     * @var int
     */
    protected $_ttr;
    
    /**
     * Get the Pheanstalk instance
     * 
     * @return Pheanstalk_Pheanstalk
     */
    public function getConnection()
    {
        //we have a stored connection
        if (!$this->_pheanstalk) {
            $_config           = $this->_getConfiguration();
            $this->_pheanstalk = new Pheanstalk_Pheanstalk($_config['host'], $_config['port']);
            $this->_priority   = $_config['priority'];
            $this->_delay      = $_config['delay'];
            $this->_ttr        = $_config['ttr'];
        }
        
        return $this->_pheanstalk;
    }
    
    /**
     * Get an array to map the beanstalkd status => magento status
     * 
     * @return array
     */
    public function getStatusMap()
    {
        return array(
                self::STATUS_READY     => Lilmuckers_Queue_Model_Queue_Task_State::QUEUED,
                self::STATUS_RESERVED  => Lilmuckers_Queue_Model_Queue_Task_State::RESERVED,
                self::STATUS_DELAYED   => Lilmuckers_Queue_Model_Queue_Task_State::DELAYED,
                self::STATUS_BURIED    => Lilmuckers_Queue_Model_Queue_Task_State::HELD
            );
    }
    
    /**
     * Get the beanstalkd connection configuration
     * 
     * @return array
     */
    protected function _getConfiguration()
    {
        //load the config from the local.xml
        $_config = Mage::getConfig()->getNode(self::BEANSTALKD_CONFIG);
        return $_config->asArray();
    }
    
    /**
     * Instantiate the connection
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _loadConnection()
    {
        $this->getConnection();
        return $this;
    }
    
    /**
     * Add the task to the queue
     * 
     * @param string                            $queue The queue to use
     * @param Lilmuckers_Queue_Model_Queue_Task $task  The task to assign
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _addToQueue($queue, Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //load the default prioriy, delay and ttr data
        $_priority = is_null($task->getPriority())  ? $this->_priority : $task->getPriority();
        $_delay    = is_null($task->getDelay())     ? $this->_delay    : $task->getDelay();
        $_ttr      = is_null($task->getTtr())       ? $this->_ttr      : $task->getTtr();
        
        //load the json string for the task
        $_data = $task->exportData();
        
        //send this data to the beanstalkd server
        $this->getConnection()
            ->useTube($queue)
            ->put($_data, $_priority, $_delay, $_ttr);
        return $this;
    }
    
    /**
     * Get the next task from the queue in question
     * 
     * @param string $queue The queue to reserve from
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _reserveFromQueue($queue)
    {
        //get the pheanstalk job
        $_job = $this->getConnection()
            ->watchOnly($queue)
            ->reserve(0);
        
        return $this->_prepareJob($_job);
    }
    
    /**
     * Get the next task from the queues in question
     * 
     * @param array $queues The queues to reserve from
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _reserveFromQueues($queues)
    {
        //remove all queues from the watchlist
        $_watchedTubes = $this->getConnection()->listTubesWatched();
        
        //unwatch the tubes
        foreach ($_watchedTubes as $_tube) {
            if (!in_array($_tube, $queues)) {
                $this->getConnection()->ignore($_tube);
            }
        }
    
        //add all the queues to the watchlist
        foreach ($queues as $_queue) {
            $this->getConnection()
                ->watch($_queue);
        }
        
        //get the next job in the qatched queues
        $_job = $this->getConnection()
            ->reserve();
        
        return $this->_prepareJob($_job);
    }
    
    /**
     * Prepare the pheanstalk job for the module as a task
     * 
     * @param Pheanstalk_Job $job The Pheanstalk job
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     * 
     * @throws Lilmuckers_Queue_Model_Adapter_Timeout_Exception When the connection times out
     */
    protected function _prepareJob(Pheanstalk_Job $job)
    {
        //if there's a timeout instead of a job then throw the timeout exception
        if (!$job) {
            throw new Lilmuckers_Queue_Model_Adapter_Timeout_Exception(
                'Timeout watching queue, no job delivered in time limit'
            );
        }
        
        //instantiate the task
        $_task = Mage::getModel(Lilmuckers_Queue_Helper_Data::TASK_HANDLER);
        $_task->setJob($job);
        
        //ensure the json data is set to the job
        $_task->importData($job->getData());
        
        //get the json data from the job
        return $_task;
    }
    
    /**
     * Touch the task to keep it reserved
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task The task to renew
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _touch(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //get the pheanstalk job
        $_job = $task->getJob();
        
        //keep the job reserved
        $this->getConnection()
            ->touch($_job);
        
        return $this;
    }
    
    /**
     * Remove a task from the queue
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue The queue handler to use
     * @param Lilmuckers_Queue_Model_Queue_Task     $task  The task to remove
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _remove(
        Lilmuckers_Queue_Model_Queue_Abstract $queue, 
        Lilmuckers_Queue_Model_Queue_Task $task
    )
    {
        //get the pheanstalk job
        $_job = $task->getJob();
        
        //delete the task from the queue
        $this->getConnection()
            ->delete($_job);
        
        return $this;
    }
    
    /**
     * Hold a task in the queue by burying it
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue The queue handler to use
     * @param Lilmuckers_Queue_Model_Queue_Task     $task  The task to hold
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _hold(
        Lilmuckers_Queue_Model_Queue_Abstract $queue, 
        Lilmuckers_Queue_Model_Queue_Task $task
    )
    {
        //get the pheanstalk job
        $_job = $task->getJob();
        
        //delete the task from the queue
        $this->getConnection()
            ->bury($_job);
        
        return $this;
    }
    
    /**
     * Unhold a task in the queue by kicking it
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue The queue handler to use
     * @param Lilmuckers_Queue_Model_Queue_Task     $task  The task to unhold
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _unhold(
        Lilmuckers_Queue_Model_Queue_Abstract $queue, 
        Lilmuckers_Queue_Model_Queue_Task $task
    )
    {
        //get the pheanstalk job
        $_job = $task->getJob();
        
        //delete the task from the queue
        $this->getConnection()
            ->kick($_job);
        
        return $this;
    }
    
    /**
     * Requeue a task
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue The queue handler to use
     * @param Lilmuckers_Queue_Model_Queue_Task     $task  The task to retry
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _retry(
        Lilmuckers_Queue_Model_Queue_Abstract $queue, 
        Lilmuckers_Queue_Model_Queue_Task $task
    )
    {
        //get the pheanstalk job
        $_job = $task->getJob();
        
        //release the task from being reserved
        $this->getConnection()
            ->release($_job);
        
        return $this;
    }
    
    /**
     * Get the job status for a given job
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task The task to map data for
     * 
     * @return array
     */
    protected function _getMappedTaskData(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //get the pheanstalk job
        $_job = $task->getJob();
        
        //get the data on the job
        $_status = $this->getConnection()
            ->statsJob($_job);
        
        return $this->_mapJobStatToTaskInfo($_status);
    }
    
    /**
     * Map the pheanstalk jobstat response to the task info for
     * the Lilmuckers_Queue module
     * 
     * @param Pheanstalk_Response_ArrayResponse $stats The stats response to map
     * 
     * @return array
     */
    protected function _mapJobStatToTaskInfo(Pheanstalk_Response_ArrayResponse $stats)
    {
        $_data = array(
            'queue'        => $stats->tube,
            'state'        => $this->_stateMap($stats->state),
            'priority'     => $stats->pri,
            'age'          => $stats->age,
            'delay'        => $stats->delay,
            'ttr'          => $stats->ttr,
            'expiration'   => $stats->time_left,
            'reserves'     => $stats->reserves,
            'timeouts'     => $stats->timeouts,
            'releases'     => $stats->releases,
            'holds'        => $stats->buries,
            'unholds'      => $stats->kicks
        );
        
        return $_data;
    }
    
    /**
     * Map the beanstalkd state to the magento state
     * 
     * @param string $state The state to map
     * 
     * @return string
     */
    protected function _stateMap($state)
    {
        $_statusMap = $this->getStatusMap();
        return $_statusMap[$state];
    }
}
