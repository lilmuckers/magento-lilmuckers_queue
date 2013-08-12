<?php

/**
 * Default queue model handler
 *
 * @category   Lilmuckers
 * @package    Lilmuckers_Queue
 * @author     Patrick McKinley <contact@patrick-mckinley.com>
 */
class Lilmuckers_Queue_Model_Queue extends Lilmuckers_Queue_Model_Queue_Abstract
{
    /**
     * The queue identifier for this queue
     */
    const DEFAULT_QUEUE_CODE = 'default';

    /**
     * Initialise the queue with the queue name 
     * 
     * @param string $queue
     * @return void
     */
    public function __construct($queue = self::DEFAULT_QUEUE_CODE)
    {
        //and run the inheritor
        parent::__construct($queue);
    }
}