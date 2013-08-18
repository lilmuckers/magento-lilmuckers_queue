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
 * Default queue model handler
 *
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Patrick McKinley <contact@patrick-mckinley.com>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
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
     * @param string $queue The queue identifier
     * 
     * @return void
     */
    public function __construct($queue = null)
    {
        //set the queue appropriately
        if (is_null($queue) || !array_key_exists('queue', $queue)) {
            $_queue = self::DEFAULT_QUEUE_CODE;
        } else {
            $_queue = $queue['queue'];
        }
        
        //and run the inheritor
        parent::__construct($_queue);
    }
}
