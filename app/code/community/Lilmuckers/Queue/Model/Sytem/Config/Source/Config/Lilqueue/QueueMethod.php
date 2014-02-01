<?php
/**
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Tim Vroom <tim@timroom.nl>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */

class Lilmuckers_Queue_Model_Sytem_Config_Source_Config_Lilqueue_QueueMethod
{
    public function toOptionArray()
    {
        return array(
            array('value' => 0, 'label' => 'Default magento cron'),
            array('value' => 1, 'label' => 'Other (Self implemented like Supervisor)')
        );
    }
}