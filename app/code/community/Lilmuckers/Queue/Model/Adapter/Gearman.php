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
 * The gearman queue adaptor
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Model_Adapter_Gearman extends Lilmuckers_Queue_Model_Adapter_Abstract
{
    /**
     * Constant for the path to the amazon sqs config
     */
    const GEARMAN_CONFIG = 'global/queue/gearman/servers';
    
    /**
     * Constant of maximum priority
     */
    const PRIORITY_MAX = 1024;
    
    /**
     * The Gearman Client object
     * 
     * @var GearmanClient
     */
    protected $_gmClient;
    
    /**
     * The Gearman Worker object
     * 
     * @var GearmanWorker
     */
    protected $_gmWorker;
    
    /**
     * Run Inline Flag
     * 
     * @var bool
     */
    protected $_runInline = true;
    
    /**
     * Get the GearmanClient instance
     * 
     * @return GearmanClient
     */
    public function getClientConnection()
    {
        //we have a stored connection
        if (!$this->_gmClient) {
            //Get the config, start the client object
            $_config         = $this->_getConfiguration();
            $this->_gmClient = new GearmanClient();
            
            //add the servers to the client
            foreach ($_config as $_server) {
                $this->_gmClient->addServer($_server['host'], $_server['port']);
            }
        }
        
        return $this->_gmClient;
    }
    
    /**
     * Get the GearmanWorker instance
     * 
     * @return GearmanWorker
     */
    public function getWorkerConnection()
    {
        //we have a stored connection
        if (!$this->_gmWorker) {
            //Get the config, start the client object
            $_config         = $this->_getConfiguration();
            $this->_gmWorker = new GearmanWorker();
            
            //add the servers to the client
            foreach ($_config as $_server) {
                $this->_gmWorker->addServer($_server['host'], $_server['port']);
            }
        }
        
        return $this->_gmWorker;
    }
    
    /**
     * Instantiate the connection
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Gearman
     */
    protected function _loadConnection()
    {
        $this->getClientConnection();
        return $this;
    }
    
    /**
     * Get the amazonsqs connection configuration
     * 
     * @return array
     */
    protected function _getConfiguration()
    {
        //load the config from the local.xml and array it
        $_config = Mage::getConfig()->getNode(self::GEARMAN_CONFIG);
        $_config = $_config->asArray();
        
        return $_config;
    }
    
    /**
     * Add the task to the queue
     * 
     * @param string                            $queue The queue to use
     * @param Lilmuckers_Queue_Model_Queue_Task $task  The task to assign
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Gearman
     */
    protected function _addToQueue($queue, Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //load the default prioriy, delay and ttr data
        $_priority = is_null($task->getPriority()) ? null : $task->getPriority();
        
        //load the json string for the task
        $_data = $task->exportData();
        
        //split the priority into ranges
        $_priorityIncrement = self::PRIORITY_MAX / 3;
        
        //Send the task to the right priority level 
        
        if ( 0 <= $_priority
            && $_priorityIncrement > $_priority
        ) {
            //high priority
            //send this data to the gearman server
            $this->getClientConnection()
                ->doHighBackground($queue, $_data);
            
        } elseif ( $_priorityIncrement <= $_priority
            && ($_priorityIncrement*2) > $_priority
        ) {
            //medium priority
            //send this data to the gearman server
            $this->getClientConnection()
                ->doBackground($queue, $_data);
        
        } elseif ( ($_priorityIncrement*2) <= $_priority
            && self::PRIORITY_MAX >= $_priority
        ) {
            //low priority
            //send this data to the gearman server
            $this->getClientConnection()
                ->doLowBackground($queue, $_data);
        
        }
        
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
        return $this->_reserveFromQueues(array($queue));
    }
    
    /**
     * Get the next task from the queues in question
     * 
     * @param array $queues The queues to reserve from
     * 
     * @return void
     * 
     * @throws Lilmuckers_Queue_Model_Adapter_Timeout_Exception When the connection times out
     */
    protected function _reserveFromQueues($queues)
    {
        //iterate through all the queues in turn
        foreach ($queues as $_queue) {
        
            //add the worker handler function to the server
            $this->getWorkerConnection()
                ->addFunction(
                    $_queue,
                    array(
                        $this,
                        'runTask'
                    )
                );
        }
    }
    
    /**
     * Run the Gearman worker
     * 
     * @param array $queues The queues to attach to
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Gearman
     */
    public function run($queues)
    {
        //first assign the watcher to the queues
        $this->_reserveFromQueues($queues);
        
        //get the worker
        $_worker = $this->getWorkerConnection();
        
        //run the worker
        while ($_worker->work() ||
               $_worker->returnCode() == GEARMAN_IO_WAIT ||
               $_worker->returnCode() == GEARMAN_NO_JOBS
        ) {
        
            //if there was a successfully executed job, then continue with
            //the loop
            if ($_worker->returnCode() == GEARMAN_SUCCESS) {
                continue;
            }
                        
            //wait for the next job to be lined up before continuing the loop
            if (!$_worker->wait()) { 
                if ($_worker->returnCode() == GEARMAN_NO_ACTIVE_FDS) { 
                    //for some reason we're not connected, so wait a bit
                    sleep(5); 
                    continue; 
                } 
                
                //there's been a failure on the wait, so break the loop
                break; 
            } 
        } 
        
        
        return $this;
    }
    
    /**
     * Run the Gearman task
     * 
     * @param GearmanJob $job The job to process
     * 
     * @return void
     */
    public function runTask($job)
    {
        //prepare the task object
        $_task = $this->_prepareJob($job);
        
        //set it as a worker task
        $_task->setIsWorker();
        
        //restart the database connection
        $this->reopenDbConnection();
        
        //run the task via the queue
        $_task->getQueue()->runTask($_task);
        
        //close the database connection
        $this->closeDbConnection();
    }
    
    /**
     * Prepare the geaman job for the module as a task
     * 
     * @param GearmanJob $job The GearmanJob job
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _prepareJob(GearmanJob $job)
    {                
        //instantiate the task
        $_task = Mage::getModel(Lilmuckers_Queue_Helper_Data::TASK_HANDLER);
        $_task->setJob($job);       
         
        //ensure the json data is set to the job
        $_task->importData($job->workload());
        
        //get the json data from the job
        return $_task;
    }
    
    /**
     * Touch the task to keep it reserved - this is not a concept recognised
     * by Gearman, so do nothing
     * 
     * @param Lilmuckers_Queue_Model_Queue_Task $task The task to renew
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Amazon
     */
    protected function _touch(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        return $this;
    }
    
    /**
     * Remove a task from the queue
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue The queue handler to use
     * @param Lilmuckers_Queue_Model_Queue_Task     $task  The task to remove
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Amazon
     */
    protected function _remove(
        Lilmuckers_Queue_Model_Queue_Abstract $queue, 
        Lilmuckers_Queue_Model_Queue_Task $task
    )
    {
        $task->getJob()->sendComplete();
        return $this;
    }
    
    /**
     * Gearman can't hold messages
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue The queue handler to use
     * @param Lilmuckers_Queue_Model_Queue_Task     $task  The task to hold
     * 
     * @TODO Enable some form of message holding
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Amazon
     */
    protected function _hold(
        Lilmuckers_Queue_Model_Queue_Abstract $queue, 
        Lilmuckers_Queue_Model_Queue_Task $task
    )
    {
        return $this;
    }
    
    /**
     * Gearman can't hold messages
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue The queue handler to use
     * @param Lilmuckers_Queue_Model_Queue_Task     $task  The task to unhold
     * 
     * @TODO Enable some form of message unholding
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Amazon
     */
    protected function _unhold(
        Lilmuckers_Queue_Model_Queue_Abstract $queue, 
        Lilmuckers_Queue_Model_Queue_Task $task
    )
    {
        return $this;
    }
    
    /**
     * Gearman can't hold messages
     * 
     * @param int                                   $number The number of held tasks to kick
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue  The queue to kick from
     * 
     * @TODO Enable some form of multiple message unholding
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Abstract
     */
    public function _unholdMultiple(
        $number,
        Lilmuckers_Queue_Model_Queue_Abstract $queue = null
    )
    {
        return $this;
    }
    
    /**
     * Requeue a task - not explicitly supported so just add it to the queue again
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue The queue handler to use
     * @param Lilmuckers_Queue_Model_Queue_Task     $task  The task to retry
     * 
     * @return Lilmuckers_Queue_Model_Adapter_Amazon
     */
    protected function _retry(
        Lilmuckers_Queue_Model_Queue_Abstract $queue, 
        Lilmuckers_Queue_Model_Queue_Task $task
    )
    {
        //create a new queue item
        $task->getQueue()->addTask($task);
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
        
        return $this->_mapJobStatToTaskInfo($_job);
    }
    
    /**
     * Map the job data to the task info for
     * the Lilmuckers_Queue module
     * 
     * @param GearmanJob $job The job data to map
     * 
     * @return array
     */
    protected function _mapJobStatToTaskInfo(array $job)
    {
        $_data = array(
            'queue'        => $job->functionName()
        );
        
        return $_data;
    }
    
    /**
     * Gearman doesn't support this
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue Queue to peek at
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _getUnreservedTask(Lilmuckers_Queue_Model_Queue_Abstract $queue)
    {
        //turn it into a task
        return $this->_dummyTask();
    }
    
    /**
     * Gearman doesn't support this
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue Queue to peek at
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _getUnreservedDelayedTask(
        Lilmuckers_Queue_Model_Queue_Abstract $queue
    )
    {
        //turn it into a task
        return $this->_dummyTask();
    }
    
    /**
     * Gearman doesn't support this
     * 
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue Queue to peek at
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _getUnreservedHeldTask(
        Lilmuckers_Queue_Model_Queue_Abstract $queue
    )
    {
        //turn it into a task
        return $this->_dummyTask();
    }
    
    /**
     * Return a dummy task
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     */
    protected function _dummyTask()
    {
        return Mage::getModel(Lilmuckers_Queue_Helper_Data::TASK_HANDLER);
    }
}
