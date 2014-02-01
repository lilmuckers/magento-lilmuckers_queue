<?php

/**
 * @category Lilmuckers
 * @package  Lilmuckers_Queue
 * @author   Tim Vroom <tim@timroom.nl>
 * @license  MIT http://choosealicense.com/licenses/mit/
 * @link     https://github.com/lilmuckers/magento-lilmuckers_queue
 */
class Lilmuckers_Queue_Helper_Indexer extends Lilmuckers_Queue_Helper_Data
{
    const XML_PATH_ENABLED      = 'lilqueue/indexer/enabled';
    const XML_PATH_RETRYTIMEOUT = 'lilqueue/indexer/retry_timeout';
    const XML_PATH_DELAY        = 'lilqueue/indexer/delay';
    const XML_PATH_PRIORITY     = 'lilqueue/indexer/priority';
    const XML_PATH_TTR          = 'lilqueue/indexer/ttr';

    /**
     * Is the indexer queue enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }

    public function getRetryTimeout()
    {
        return Mage::getStoreConfig(self::XML_PATH_RETRYTIMEOUT);
    }

    public function getDelay()
    {
        return Mage::getStoreConfig(self::XML_PATH_DELAY);
    }

    public function getPriority()
    {
        return Mage::getStoreConfig(self::XML_PATH_PRIORITY);
    }

    public function getTtr()
    {
        return Mage::getStoreConfig(self::XML_PATH_TTR);
    }
}