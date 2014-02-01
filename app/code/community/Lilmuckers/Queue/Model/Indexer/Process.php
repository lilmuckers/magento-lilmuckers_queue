<?php
/**
 * NicheCommerce
 * 
 * @category    NicheCommerce
 * @package     NicheCommerce
 * @copyright   Copyright (c) 2014 NicheCommerce. (http://nichecommerce.nl)
 * @author      Tim Vroom (tim@nichecommerce.nl)
 */
class Lilmuckers_Queue_Model_Indexer_Process extends Mage_Index_Model_Process
{
    /**
     * @var Lilqueue_Model_Queue $_queue
     */
    protected $_queue;

    /**
     *
     */
    public function reindexAll()
    {
        $helper = Mage::helper('lilqueue/indexer');
        $use_original_method = true;
        if ($helper->isEnabled()) {
            try {
                $queue = $this->getQueue('indexer');
                $task = Mage::getModel('lilqueue/queue_task');
                $task->setData($this->getData());
                $task->setTask('indexer');
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
     * @param $queue
     */
    public function setQueue($queue)
    {
        $this->_queue = $queue;
    }

    public function reindexAllJob()
    {
        return parent::reindexAll();
    }

    public function processEvent(Mage_Index_Model_Event $event)
    {
        if (!$this->matchEvent($event)) {
            return $this;
        }
        if ($this->getMode() == self::MODE_MANUAL) {
            $this->changeStatus(self::STATUS_REQUIRE_REINDEX);
            return $this;
        }

        $helper = Mage::helper('lilqueue/indexer');
        $use_original_method = true;
        if ($helper->isEnabled()) {
            try {
                $queue = $this->getQueue('indexer');
                $task = Mage::getModel('lilqueue/queue_task');
                $eventPrepared = $this->convertEvent($event);
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
    public function processEventJob(Mage_Index_Model_Event $event)
    {
        $event = $this->convertEvent(unserialize($event));
        return parent::processEvent($event);
    }

    public function convertEvent($event)
    {
        if (is_object($event)) {
            try {
                if (is_a($event->getDataObject()->getProduct(), 'Mage_Catalog_Model_Product')) {
                    $object = $event->getDataObject();
                    $event->setDataObject(get_class($object) . ':::'. $object->getId());
                }
                if (is_string($event->getDataObject()) && strpos($event->getDataObject(), ':::') !== false) {
                    $explode = explode(':::', $event->getDataObject());
                    $object = new $explode[0]();
                    $object->load($explode[1]);
                    $event->setDataObject($object);
                }
            } catch(Exception $e) {

            }
        }
        return $event;
    }
}