# Lilmuckers_Queue

A generic multi-adapter queuing system for magento.

This module is primarily aimed at developers who need a reliable queue management system for performing asyncronous tasks. This was built because there wasn't a decent queue management module within magento or the community that filled all my requirements. This also avoids the issues that can exist with database and cron based queueing systems (such as available with Magent Enterprise)

This module has been built to be a framework for both the producer (the task adding to the queue) and the worker (the task that performs items on the queue).

Each queue has it's own handler, so multiple tasks that are a part of the same queue can easily share resources and save memory, and it is easy to create new queues with new queue handlers. Currently this is provided with only one queue (`default`) and one worker (`test`) that merely removes itself from the queue. However this should act as enough of a framework for a developer to base his or her work upon.

## Supported Queue Backends
 * beanstalkd - http://kr.github.io/beanstalkd/
  * Using Pheanstalk - https://github.com/pda/pheanstalk/
 * Amazon SQS - http://aws.amazon.com/sqs/
  * Using Amazon AWS SDK
 * Gearman - http://gearman.org/
  * Using PHP Gearman Library - http://www.php.net/manual/en/book.gearman.php
  
## Installation
I built this with **modgit** (https://github.com/jreinke/modgit) in mind - and so if you have modgit installed:

```bash
$ modgit add lilqueue https://github.com/lilmuckers/magento-lilmuckers_queue.git
``` 

Or you can just merge the files manually into your magento project. This will be added as a Community pear module once it's been fully tested and deemed stable.

## Usage

### Configuration
The configuration is similar to the `cache` configuration, and is held within the `local.xml` file. For a very simple **beanstalkd** configuration you'd merge this into your `local.xml`:

```xml
<?xml version="1.0"?>
<config>
    <global>
    
        <queue>
            <backend>beanstalkd</backend>
            <beanstalkd>
                <servers>
                    <server>
                        <host>127.0.0.1</host>
                    </server>
                </servers>
            </beanstalkd>
        </queue>
        
    </global>

</config>

```

For a simple **Amazon SQS** configuration you'd merge this into your `local.xml`:

```xml
<?xml version="1.0"?>
<config>
    <global>
    
        <queue>
            <backend>amazonsqs</backend>
            <amazonsqs>
                <connection>
                    <key>{{AWS KEY}}</key>
                    <secret>{{AWS SECRET}}</secret>
                    <region>{{AWS REGION}}</region>
                </connection>
            </amazonsqs>
        </queue>
        
    </global>

</config>

```

For a **Gearman** configuration, you'd merge this into your `local.xml`:

```xml
<?xml version="1.0"?>
<config>
    <global>
    
        <queue>
            <backend>gearman</backend>
        </queue>
        
    </global>
</config>
```

#### Advanced Configuration
See the file `app/etc/local.xml.queuesample` for advanced configuration examples.

### Creating a queue handler, defining workers, and sending a task to the queue
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

 * **[optional]** Set a priority, delay and ttr (time-to-run - time limit for the task to be run before it's reassigned to the queue)

```php
<?php
$_task->setPriority(100)
    ->setDelay(60)
    ->setTtr(60);
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



### Running the workers
The workers are run using a shell script located within `/shell/`, and you can run all queues, or a subset of queues, allowing you to split the different queues to different servers, or whatever arrangement you so wish, to allow maximum flexibility. The script can also be run multiple times to allow for multiple workers

```bash
$ php /path/to/magento/shell/queue.php --watch <queues> 
```

## Building An Adapter
If you wish to use the framework with another queueing backend then you'll need to build an adapter to support the interface between the module and the queueing system.

### 1. Define the adapter model
First off you need to define the adapter model within the `config.xml` (preferably of your own module - but if you're contributing to this module, then... woo!)
```xml
<?xml version="1.0"?>
<config>
    <global>
    
        <queue>
            <adapters>
                <adapterName>
                    <class>module/adapter_path</class>
                </adapterName>
            </adapters>
        </queue>
        
    </global>
</config>
```

### 2. Build the model class 
Now you need to build the adapter class. The abstract/interface was built with the **beanstalkd** methodology in mind, so to be truly compatible you'll have to come up with equivilent functionality. This should extend `Lilmuckers_Queue_Model_Adapter_Abstract`. See that class for a list of abstract methods.

### 3. Configure Module
Within the `local.xml` you'll now need to set the **backend value** to the code for the adapter you assigned in step 1 (in this example `adapterName`).

```xml
<?xml version="1.0"?>
<config>
    <global>
    
        <queue>
            <backend>adapterName</backend>
        </queue>
        
    </global>

</config>

```

### 4. Done
Have a nice cup of tea and a sit down.

## Testing
The unit tests are built with [EcomDev_PHPUnit](https://github.com/EcomDev/EcomDev_PHPUnit) and should run with the standard test-suite.

### Test Coverage
The tests cover:
 * Workers
 * Queues
 * Tasks
 * beanstalkd adapter

The AmazonSQS adapter isn't covered by the unit tests because of reasons.

### Connection Testing
You can manually send messages to the queue with the following command:
```bash
 $ cd /path/to/magento/shell/
 $ php -f queue.php --send "default:test:hello"
```

This will send a message to the configured queue to use the default queue, run the test worker, and send the message of "hello". This could then be viewed with your favorite monitoring software for your queue backend.

## TODO
 * Set AWS and Pheanstalk to be installed with **PEAR** or **Composer**
 * Do something sensible with **held** tasks on **Amazon SQS** and **Gearman**
 * Backend interface to view queue statistics (tricky to make "multi-adapter")
 * Error reporting
 * Rabbit MQ Support
 * NoSQL Queue support (for example - mongodb, redis, etc)
 * Memcached queue support
 * SQL Queue Support (for graceful decay if situations change - not because I think it's a good idea)

## Gotchas
There's a few things I've tripped over when using this module in testing and in implementation:
 * **Queues aren't reliable**
  * This is that you can't count on a queue having been used before - as a task can be run at any point after the queue has been kicked off. So you can't rely on any task having been run before. The workaround for this is that if you're using the queue handler to cache a Magento model for reuse - your queue handler should be the one to instantiate the model within a getter method.
  * Don't set data to a queue externally from within a task if it's **required** by another task. Have the queue able to create the data itself.
  * If you do need to set data to a queue from within a task - make sure that any subsequent tasks that need to use this data have appropriate error checks to deal with the event that the data isn't there.
 * **Store Contexts**
  * Remember that a task is natively run within the `admin` store - so using `Mage::app()->getStore()` will return the admin store view.
  * If you need to run a task within a specific store view (such as getting config values via `Mage::getStoreConfig()`, loading collections in the correct store context, and so forth) use the `$task->getStore()` method to get the store view that the task was created from.
 * **Memory**
  * Ensure that workers clean up after themselves, removing items from memory that are no longer needed (for example - products that were loaded for a job, but aren't needed anymore). This is because the worker script is a long running process, so if things are left floating the process can quickly run out of memory and need to be restarted.
  * Process management tools such as **supervisord** (http://supervisord.org/) can be used to restart a task when a memory limit is reached - but ensure that if your task uses a lot of memory just as a matter of course - that it isn't tripping this limit.
 * **Amazon SQS**
  * Due to the fact **SQS** doesn't explicitly support watching multiple queues, if you are running one worker stream (`php -f shell/queue.php --watch all`) it can take time for a queue message to be received. It is currently designed to wait 5 seconds on each queue for a message before moving on to the next one. This is slower than 0 second polling, but much less cpu and networking intensive. You can override this by specifying a different **wait** time in the `local.xml` on the path `global/queue/amazonsqs/connection/wait` (Default is **5** seconds). Alternatively you can specify a worker stream for each queue, which will handily overcome the issue.
  * Due to the potential delay you can experience, some tasks may not be executed as fast as you would prefer. This could be an issue when using an asyncronous indexing module - where an index may not be updated for 20 seconds or so after the save has completed. This could cause some confusing effects to the user.
  * **SQS** doesn't support message **priority** - so the queue doesn't process high priority events faster than lower priority ones.
  * **SQS** doesn't support explicit **retry** on a message, so instead the module waits for the **ttr** to lapse and the message to be put back in the queue organically.
  * **SQS** doesn't support explicit **holding** or **unholding** a task. As such these things don't actually do anything within the system. So a task that has been put on **hold** will instead be retried on a loop. There is a **TODO** to look at improving this.
 * **Gearman**
  * As with **Amazon SQS**, **Gearman** doesn't support explicitly holding and unholding tasks. However, contrary to **SQS**, tasks will just be dropped from the queue, so at least these ones won't loop, however, they won't be stored for review.
  * Priority isn't as granular as it is with **beanstalkd** so will split the priority number into ranges and define them as `0-340` - High, `341-682` - Normal, `683-1024` - Low.