<?php
/**
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Tim Vroom <tim@timroom.nl>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Model_Observer
{
    public function lilqueue_index($schedule){
        $method = Mage::getStoreConfig('lilqueue/general/queue_method');
        // do we want to use the cronjob method?
        if ($method != 0) {
            return;
        }
        $helper = Mage::helper('lilqueue');
        foreach ($helper->getQueues() as $code => $label) {
            $queue = $helper->getQueue($code, true);
            $counter = 0;
            try {
                while ($task = $queue->getNextUnreservedTask()) {
                    $queue->runTask($task);
                    $counter++;
                }
            } catch (Pheanstalk_Exception_ServerException $e) {
                if ($e->getMessage() != "NOT_FOUND: There are no jobs in the 'ready' status") {
                    Mage::logException($e);
                }
            }
            Mage::log("Finished %s jobs", $counter);
        }
    }
}
