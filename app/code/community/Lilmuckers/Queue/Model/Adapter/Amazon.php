<?php 
/**
 * Magento Simple Asyncronous Queuing Module
 *
 * @category    Lilmuckers
 * @package     Lilmuckers_Queue
 * @copyright   Copyright (c) 2013 Patrick McKinley (http://www.patrick-mckinley.com)
 * @license     http://choosealicense.com/licenses/mit/
 */

//include the AWS auto-loader, because the weird namespacing doesn't play nice
//with Magentos auto-loader.
require_once BP.DS.'lib/Aws/aws-autoloader.php';

//use the namespaces we need
use Aws\Sqs\Enum\QueueAttribute;
use Aws\Sqs\SqsClient;
use Aws\Sqs\Enum\MessageAttribute;

/**
 * The Amazon SQS queue adaptor
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Model_Adapter_Amazon extends Lilmuckers_Queue_Model_Adapter_Abstract
{
    /**
     * Constant for the path to the amazon sqs config
     */
    const AMAZONSQS_CONFIG = 'global/queue/amazonsqs/connection';
    
    /**
     * The SQS Client object
     * 
     * @var Aws\Sqs\SqsClient
     */
    protected $_sqsClient;
    
    /**
     * A cache of queue URLs
     * 
     * @var array
     */
    protected $_queueUrls = array();
    
    /**
     * A map for our variables to the queue attributes
     * 
     * @var array
     */
    protected $_queueAttrMap = array(
        'ttr'      => QueueAttribute::VISIBILITY_TIMEOUT,
        'delay'    => QueueAttribute::DELAY_SECONDS,
        'max_size' => QueueAttribute::MAXIMUM_MESSAGE_SIZE,
        'wait'     => QueueAttribute::RECEIVE_MESSAGE_WAIT_TIME_SECONDS
    );
    
    /**
     * The default attributes to start queues with
     * 
     * @var array
     */
    protected $_queueDefaultAttr;
    
    /**
     * Get the Aws Client instance
     * 
     * @return Aws\Sqs\SqsClient
     */
    public function getConnection()
    {
        //we have a stored connection
        if (!$this->_sqsClient) {
            $_config           = $this->_getConfiguration();
            $this->_sqsClient  = SqsClient::factory(
                array(
                    'key'    => $_config['key'],
                    'secret' => $_config['secret'],
                    'region' => $_config['region']
                )
            );
        }
        
        return $this->_sqsClient;
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
     * Get the amazonsqs connection configuration
     * 
     * @param string $value Value to retrieve from config
     * 
     * @return array
     */
    protected function _getConfiguration($value = null)
    {
        //load the config from the local.xml and array it
        $_config = Mage::getConfig()->getNode(self::AMAZONSQS_CONFIG);
        $_config = $_config->asArray();
        
        //return the requested value if isset
        if (!is_null($value) && array_key_exists($value, $_config)) {
            return $_config[$value];
        }
        return $_config;
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
        $_delay    = is_null($task->getDelay())     ? null    : $task->getDelay();
        
        //load the json string for the task
        $_data = $task->exportData();
        
        //get the queue url for this task
        $_queueUrl = $this->_getQueueUrl($queue);
        
        //start building the task parameters
        $_params = array(
            'QueueUrl'     => $_queueUrl,
            'MessageBody'  => $_data,
        );
        
        //if delay was specified:
        if ($_delay) {
            $_params['DelaySeconds'] = $_delay;
        }
        
        //send this data to the beanstalkd server
        $this->getConnection()
            ->sendMessage($_params);
            
        return $this;
    }
    
    /**
     * Get the Amazon SQS Queue URL for an identifier
     * 
     * @param string $queue The identifier for the queue
     * 
     * @return string
     */
    protected function _getQueueUrl($queue)
    {
        //check if we know this data...
        if (!array_key_exists($queue, $this->_queueUrls)) {
            //build queue parameters
            $_queueParams = array(
                'QueueName'  => $queue,
            );
            
            //Append the additional queue parameters if applicable
            if($_queueAttr = $this->_getDefaultQueueAttr()){
                $_queueParams['Attributes'] = $_queueAttr;
            }
            
            //create the queue
            $_result = $this->getConnection()->createQueue($_queueParams);
            
            //get the queue url
            $this->_queueUrls[$queue] = $_result->get('QueueUrl');
        }
        
        return $this->_queueUrls[$queue];
    }
    
    /**
     * Build an array of additional data
     * 
     * @return array|bool
     */
    protected function _getDefaultQueueAttr()
    {
        if (!$this->_queueDefaultAttr) {
            $_config = $this->_getConfiguration();
            
            //instantiate the array first of all
            $this->_queueDefaultAttr = array();
            
            //iterate through the config, and map applicable values
            foreach ($_config as $_key => $_value) {
                if (array_key_exists($_key, $this->_queueAttrMap)) {
                    $this->_queueDefaultAttr[$this->_queueAttrMap[$_key]] = $_value;
                }
            }
        }
        
        //if there's nothing, return a false
        if (empty($this->_queueDefaultAttr)) {
            return false;
        }
        
        //otherwise return the data
        return $this->_queueDefaultAttr;
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
        //get the queue url we're interested in
        $_queueUrl = $this->_getQueueUrl($queue);
    
        //get the amazonsqs job
        $_job = $this->getConnection()
            ->receiveMessage(
                array(
                    'QueueUrl'       => $_queueUrl,
                    'AttributeNames' => array(
                        MessageAttribute::ALL
                    )
                )
            );
        
        return $this->_prepareJob($_job, $queue);
    }
    
    /**
     * Get the next task from the queues in question
     * 
     * @param array $queues The queues to reserve from
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     * 
     * @throws Lilmuckers_Queue_Model_Adapter_Timeout_Exception When the connection times out
     */
    protected function _reserveFromQueues($queues)
    {
        //iterate through all the queues in turn
        foreach ($queues as $_queue) {
            try{
                $_task = $this->_reserveFromQueue($_queue);
            } catch(Lilmuckers_Queue_Model_Adapter_Timeout_Exception $e) {
                //ignore and continue
                continue;
            }
            return $_task;
        }
        
        //throw a timeout exception
        throw new Lilmuckers_Queue_Model_Adapter_Timeout_Exception(
            'Timeout watching queue, no job delivered in time limit'
        );
    }
    
    /**
     * Prepare the amazonsqs job for the module as a task
     * 
     * @param Guzzle\Service\Resource\Model $job   The AmazonSQS job
     * @param string                        $queue The queue identifier
     * 
     * @return Lilmuckers_Queue_Model_Queue_Task
     * 
     * @throws Lilmuckers_Queue_Model_Adapter_Timeout_Exception When the connection times out
     */
    protected function _prepareJob(Guzzle\Service\Resource\Model $job, $queue)
    {
        //if there's a timeout instead of a job then throw the timeout exception
        if (!$job->get('Messages')) {
            throw new Lilmuckers_Queue_Model_Adapter_Timeout_Exception(
                'Timeout watching queue, no job delivered in time limit'
            );
        }
        
        //pull off the only thing we're interested in - the first message
        $_job = array_shift(array_values($job->get('Messages')));
        
        //set the queue
        $_job['Queue'] = $queue;
        
        //instantiate the task
        $_task = Mage::getModel(Lilmuckers_Queue_Helper_Data::TASK_HANDLER);
        $_task->setJob($_job);
        
        //ensure the json data is set to the job
        $_task->importData($_job['Body']);
        
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
            ->changeMessageVisibility(
                array(
                    'QueueUrl'          => $this->_getQueueUrl($_job['Queue']),
                    'ReceiptHandle'     => $_job['ReceiptHandle'],
                    'VisibilityTimeout' => $this->_getConfiguration('ttr')
                )
            );
        
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
        
        //keep the job reserved
        $this->getConnection()
            ->deleteMessage(
                array(
                    'QueueUrl'          => $this->_getQueueUrl($_job['Queue']),
                    'ReceiptHandle'     => $_job['ReceiptHandle']
                )
            );
        
        return $this;
    }
    
    /**
     * AmazonSQS can't hold messages
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
        return $this;
    }
    
    /**
     * AmazonSQS can't hold messages
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
        return $this;
    }
    
    /**
     * AmazonSQS can't hold messages
     * 
     * @param int                                   $number The number of held tasks to kick
     * @param Lilmuckers_Queue_Model_Queue_Abstract $queue  The queue to kick from
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
     * Requeue a task - not explicitly supported so just let the ttr lapse
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
        //instead of explicitly setting an item to retry - let it timeout
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
     * Map the AmazonSQS data response to the task info for
     * the Lilmuckers_Queue module
     * 
     * @param array $stats The stats response to map
     * 
     * @return array
     */
    protected function _mapJobStatToTaskInfo(array $stats)
    {
        $_data = array(
            'queue'        => $stats['Queue'],
            'age'          => time() - $stats['Attributes']['SentTimestamp'],
            'ttr'          => $this->_getConfiguration('ttr'),
            'reserves'     => $stats['Attributes']['ApproximateReceiveCount'],
        );
        
        return $_data;
    }
    
    /**
     * AmazonSQS doesn't support this
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
     * Amazon SQS doesn't support this
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
     * AmazonSQS doesn't support this
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