# Lilmuckers_Queue

A generic multi-adapter queuing system for magento.

This module is primarily aimed at developers who need a reliable queue management system for performing asyncronous tasks. This was built because there wasn't a decent queue management module within magento or the community that filled all my requirements. This also avoids the issues that can exist with database and cron based queueing systems (such as available with Magent Enterprise)

This module has been built to be a framework for both the producer (the task adding to the queue) and the worker (the task that performs items on the queue).

Each queue has it's own handler, so multiple tasks that are a part of the same queue can easily share resources and save memory, and it is easy to create new queues with new queue handlers. Currently this is provided with only one queue (`default`) and one worker (`test`) that merely removes itself from the queue. However this should act as enough of a framework for a developer to base his or her work upon.

## Supported Queue Backends
 * beanstalkd - http://kr.github.io/beanstalkd/
  * Using Pheanstalk - https://github.com/pda/pheanstalk/

## Usage

### Creating a queue handlerm defining workersm, and sending a task to the queue
 * Define a queue in you modules `config.xml`, with the workers that exist in that queue. All workers are run as `singletons`.
  ```xml
<?xml version="1.0"?>
<config>
...
    <queues>
        <queueName>
            <label>The Queue Name</label>
            <class>module/queueHandler</class>
            <workers>
                <taskName>
                    <class>module/worker</class>
                    <method>methodName</method>
                </taskName>
            </workers>
        </queueName>
    </queues>
    
</config>
   ```
 * Call the queue handler
  ```php
<?php
$_queue = Mage::helper('lilqueue')->getQueue('queueName');
  ```
 * Generate a task, providing the task identifier, the data for the task to use, and the store to provide to the task to run with.
  ```php
<?php
$_task = Mage::helper('lilqueue')->createTask('taskName', array('data'=>'to provide', 'to'=>'the worker'), $storeToRunAs);
  ```
 * Send the task to the queue
  ```php
<?php
$_queue->addTask($_task);
  ```
  
### Workers
Workers are even easier, as they're just a method that receive the task as an argument. They must set a status to the task once they're done. 
If a task is taking longer than the allowed time by the queue backend, it can extend that time by calling `touch()` on the task.
```php
<?php

class My_Module_Model_Worker extends Lilmuckers_Queue_Model_Worker_Abstract
{
    public function methodName(Lilmuckers_Queue_Model_Queue_Task $task)
    {
        //get the store assigned with the task (defaults to the store that was running when the task was assigned)
        $store = $task->getStore();
        
        //get the queue handler for this queue
        $queue = $task->getQueue();
        
        //get the data assigned with the task
        $data = $task->getData();  // $task->getSpecificData();
        
        //This task ended properly
        $task->success();
        
        //this task needs to be repeated
        $task->retry();
        
        //this task errored and we should drop it from the queue for later examination
        $task->hold();
        
        //this worker is taking a long time, we should extend the time we're allowed to use it
        $task->touch();
    }
}
```
