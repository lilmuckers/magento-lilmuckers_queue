<?php 

/**
 * The beanstalkd queue adaptor
 *
 * @category   Lilmuckers
 * @package    Lilmuckers_Queue
 * @author     Patrick McKinley <contact@patrick-mckinley.com>
 */
class Lilmuckers_Queue_Model_Adapter_Beanstalk extends Lilmuckers_Queue_Model_Adapter_Abstract
{
    /**
     * Constant for the path to the beanstalkd config
     */
    const BEANSTALKD_CONFIG = 'global/queue/beanstalkd/servers/server';
    
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
        if(!$this->_pheanstalk)
        {
            $_config = $this->_getConfiguration();
            $this->_pheanstalk = new Pheanstalk_Pheanstalk($_config['host'], $_config['port']);
            $this->_priority   = $_config['priority'];
            $this->_delay      = $_config['delay'];
            $this->_ttr        = $_config['ttr'];
        }
        
        return $this->_pheanstalk;
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
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @param int $priority
     * @param int $delay
     * @param int $ttr
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _addToQueue($queue, Lilmuckers_Queue_Model_Queue_Task $task, $priority = null, $delay = null, $ttr = null)
    {
        //load the default prioriy, delay and ttr data
        $_priority = is_null($priority)  ? $this->_priority : $priority;
        $_delay    = is_null($delay)     ? $this->_delay    : $delay;
        $_ttr      = is_null($ttr)       ? $this->_ttr      : $ttr;
        
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
     * @param string $queue
     * @throws Lilmuckers_Queue_Model_Queue_Task_Exception
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _reserveFromQueue($queue)
    {
        //get the pheanstalk job
        $_job = $this->getConnection()
            ->watchOnly($queue)
            ->reserve(0);
        
        //if there's a timeout instead of a job then throw the timeout exception
        if(!$_job){
            throw new Lilmuckers_Queue_Model_Adapter_Timeout_Exception('Timeout watching queue: '.$queue);
        }
        
        //instantiate the task
        $_task = Mage::getModel(Lilmuckers_Queue_Helper_Data::TASK_HANDLER);
        $_task->setJob($_job);
        
        //ensure the json data is set to the job
        $_task->importData($_job->getData());
        
        //get the json data from the job
        return $_task;
        
    }
    
    /**
     * Touch the task to keep it reserved
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task
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
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _remove($queue, Lilmuckers_Queue_Model_Queue_Task $task)
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
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _hold($queue, Lilmuckers_Queue_Model_Queue_Task $task)
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
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _unhold($queue, Lilmuckers_Queue_Model_Queue_Task $task)
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
     * @param string $queue
     * @param Lilmuckers_Queue_Model_Queue_Task $task
     * @return Lilmuckers_Queue_Model_Adapter_Beanstalk
     */
    protected function _retry($queue, Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //get the pheanstalk job
        $_job = $task->getJob();
        
        //release the task from being reserved
        $this->getConnection()
            ->release($_job);
        
        return $this;
    }
    
}