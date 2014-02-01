<?php
/**
 * NicheCommerce
 * 
 * @category    NicheCommerce
 * @package     NicheCommerce
 * @copyright   Copyright (c) 2014 NicheCommerce. (http://nichecommerce.nl)
 * @author      Tim Vroom (tim@nichecommerce.nl)
 */ 
class Lilmuckers_Queue_Helper_Indexer extends Lilmuckers_Queue_Helper_Data
{
    const XML_PATH_ENABLED = 'lilqueue/indexer/enabled';

    public function isEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }
}