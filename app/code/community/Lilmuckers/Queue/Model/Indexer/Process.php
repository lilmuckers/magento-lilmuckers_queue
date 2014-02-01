<?php

/**
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Tim Vroom <tim@timroom.nl>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Model_Indexer_Process extends Mage_Index_Model_Process
{
    /**
     * @var Lilqueue_Model_Queue $_queue
     */
    protected $_queue;

    /**
     * Get the indexer queue
     *
     * @param null $name
     *
     * @return Lilqueue_Model_Queue
     */
    public function getQueue($name = null)
    {
        if ($this->_queue == null) {
            $queue = Mage::getModel('lilqueue/queue', array('queue' => $name));
            $this->setQueue($queue);
        }

        return $this->_queue;
    }

    /**
     * Set the indexer queue
     *
     * @param $queue
     */
    public function setQueue(Lilmuckers_Queue_Model_Queue $queue)
    {
        $this->_queue = $queue;
    }

    /**
     * Put the reindexAll into the queue
     * When something fails just use the parents function
     *
     * @return $this
     */
    public function reindexAll()
    {
        $helper              = Mage::helper('lilqueue/indexer');
        $use_original_method = true;
        if ($helper->isEnabled()) {
            try {
                $task = Mage::getModel('lilqueue/queue_task');
                $task->setData($this->getData());
                $task->setTask('indexer');
                $task->setPriority($helper->getPriority());
                $task->setTtr($helper->getTtr());
                $task->setDelay($helper->getDelay());

                $queue = $this->getQueue('indexer');
                $queue->addTask($task);
                $use_original_method = false;
            } catch (Exception $e) {
            }
        }

        if ($use_original_method) {
            $function = __FUNCTION__;

            return parent::$function();
        }

        return $this;
    }

    /**
     * Allow the worker to call the reindexJob
     *
     * @return $this
     */
    public function reindexAllJob()
    {
        return parent::reindexAll();
    }

    /**
     * Put the processEvent into
     *
     * @param Mage_Index_Model_Event $event
     *
     * @return $this|Mage_Index_Model_Process
     */
    public function processEvent(Mage_Index_Model_Event $event)
    {
        if (!$this->matchEvent($event)) {
            return $this;
        }
        if ($this->getMode() == self::MODE_MANUAL) {
            $this->changeStatus(self::STATUS_REQUIRE_REINDEX);

            return $this;
        }

        $helper              = Mage::helper('lilqueue/indexer');
        $use_original_method = true;
        if ($helper->isEnabled()) {
            try {
                $queue         = $this->getQueue('indexer');
                $task          = Mage::getModel('lilqueue/queue_task');
                $eventPrepared = $this->_convertEvent($event);
                $task->setData(array('event' => serialize($eventPrepared), 'data' => $this->getData()));
                $task->setTask('indexer');
                $queue->addTask($task);
                // restore event
                $this->convertEvent($event);
                $use_original_method = false;
            } catch (Exception $e) {
            }
        }

        if ($use_original_method) {
            $function = __FUNCTION__;

            return parent::$function($event);
        }

        return $this;
    }

    /**
     * Allow the worker to run the processEvent job
     *
     * @param Mage_Index_Model_Event $event
     *
     * @return Mage_Index_Model_Process
     */
    public function processEventJob(Mage_Index_Model_Event $event)
    {
        $event = $this->_convertEvent(unserialize($event));

        return parent::processEvent($event);
    }

    /**
     * Serialize cannot handle simpleXml objects, lets figure out which ones has to be remodelled for that.
     *
     * @param $event
     *
     * @return mixed
     */
    protected function _convertEvent($event)
    {
        if (is_object($event)) {
            try {
                if (is_a($event->getDataObject()->getProduct(), 'Mage_Catalog_Model_Product')) {
                    $object = $event->getDataObject();
                    $event->setDataObject(get_class($object) . ':::' . $object->getId());
                }
                if (is_string($event->getDataObject()) && strpos($event->getDataObject(), ':::') !== false) {
                    $explode = explode(':::', $event->getDataObject());
                    $object  = new $explode[0]();
                    $object->load($explode[1]);
                    $event->setDataObject($object);
                }
            } catch (Exception $e) {
            }
        }

        return $event;
    }
}