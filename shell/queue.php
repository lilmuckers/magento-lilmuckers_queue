<?php
/**
 * Magento Simple Asyncronous Queuing Module
 *
 * @category    Lilmuckers
 * @package     Lilmuckers_Shell
 * @copyright   Copyright (c) 2013 Patrick McKinley (http://www.patrick-mckinley.com)
 * @license     http://choosealicense.com/licenses/mit/
 */
 
require_once 'abstract.php';

/**
 * Queue processor script
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Shell
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Shell_Queue extends Mage_Shell_Abstract
{
    /**
     * The keyword for all queues
     */
    const KEYWORD_ALL = 'all';

    /**
     * The queue helper
     * 
     * @var Lilmuckers_Queue_Helper_Data
     */
    protected $_helper;
    
    /**
     * Run the command
     * 
     * @return Lilmuckers_Shell_Queue
     */
    public function run()
    {
        //instantiate the helper
        $this->_helper = $this->_factory->getHelper('lilqueue');
        
        //checkif the user wanted a list
        if ($this->getArg('list')) {
            //the user wants to list all the queues
            return $this->_list();
        }
        
        //check if the user wanted to watch a queue
        if ($this->getArg('watch')) {
            //get the selected queues
            $_queues = $this->getArg('watch');
            
            //get the list of queues to watch
            $_queues = $this->_getQueues($_queues);
            
             return $this->_watch(array_keys($_queues));
        }
        
        //check if we're sending something ot the queue
        if ($this->getArg('send')) {
            //get the send param and validate it
            $_send = $this->getArg('send');
            if (false !== strpos($_send, ':')) {
                list($_queue, $_task, $_message) = explode(':', $_send);
                return $this->_send($_queue, $_task, $_message);
            }
            
            die("An invalid send message was specified\n");
        }
        
        //if nothing called, just do the help
        echo $this->usageHelp();
        
        return $this;
    }
    
    /**
     * Send an arbitrary message to the active queue
     * 
     * @param string $queue   Name of a queue to send to
     * @param string $task    Name of the task to run
     * @param string $message Message to send to the queue
     * 
     * @return void
     */
    protected function _send($queue, $task, $message)
    {
        //get the queue handler
        $_queue = Mage::helper('lilqueue')->getQueue($queue);
            
        //instantiate the task
        $_task = Mage::helper('lilqueue')->createTask(
            $task, 
            array('message' => $message)
        );
            
        //send to the queue
        $_queue->addTask($_task);
    }
    
    /**
     * Get a list of the specified queues
     * 
     * @param string $queues String to define the queues to run
     * 
     * @return array
     */
    protected function _getQueues($queues = self::KEYWORD_ALL)
    {
        //load all the queue data
        $_queuesInfo = $this->_helper->getQueues();
        
        //instantiate the queues array
        $_queues = array();
        
        //arrange the queue data
        foreach ($_queuesInfo as $_name => $_data) {
            $_queues[$_name] = $_data['label'];
        }
        
        //select what to return
        if ($queues == self::KEYWORD_ALL) {
            // the guy wants them all
            return $_queues;
        }
        
        //split the selection by comma
        $_selectedQueues = explode(',', $queues);
        
        //we want to return only the ones we want
        $_selected = array();
        foreach ($_selectedQueues as $_chosen) {
            if (array_key_exists($_chosen, $_queues)) {
                $_selected[$_chosen] = $_queues[$_chosen];
            }
        }
        return $_selected;
    }
    
    /**
     * List the queues
     * 
     * @return Lilmuckers_Shell_Queue
     */
    protected function _list()
    {
        //get all the queues
        $_queues = $this->_getQueues();
        
        //display all queue codes
        foreach ($_queues as $_code=>$_label) {
            echo sprintf('%-30s', $_code);
            echo $_label . "\n";
        }
        
        //end
        return $this;
    }
    
    /**
     * Watch script that will loop ad-infinitum
     * 
     * @param array $queues Array of queues to watch
     * 
     * @return void
     */
    protected function _watch($queues)
    {
        //start the infinite while loop
        while (true) {
            try {
            
                //watch the queue list
                $_task = $this->_helper->getAdapter()->getTask($queues);
                
                //flag the task as a worker so it instantiates the queue properly
                $_task->setIsWorker();
                
                //run the task via the queue
                $_task->getQueue()->runTask($_task);
                
            } catch(Lilmuckers_Queue_Model_Adapter_Timeout_Exception $e) {
                //timeout waiting for job, ignore.
            }
        }
    }
    
    /**
     * Retrieve Usage Help Message
     * 
     * @return string
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f queue.php -- [options]

  --list                          Get a list of all valid queues
  --watch <queue>                 Start processing a given queue set
  --send <queue>:<task>:<message> Send a message to the queue for testing
  help                            This help

  <queue>     Comma separated queue codes or value "all" for all queues

USAGE;
    }
}

//run the shell script
$shell = new Lilmuckers_Shell_Queue();
$shell->run();
