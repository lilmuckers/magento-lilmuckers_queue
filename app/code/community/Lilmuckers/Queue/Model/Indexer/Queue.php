<?php
/**
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Tim Vroom <tim@timroom.nl>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Model_Indexer_Queue extends Lilmuckers_Queue_Model_Queue_Abstract
{
    public function __construct($name = null){
        if ($name == null) {
            $name = array('queue' => 'indexer');
        }
        parent::__construct($name);
    }
}