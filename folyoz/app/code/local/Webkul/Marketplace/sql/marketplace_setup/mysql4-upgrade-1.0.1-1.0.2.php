<?php
$installer = $this;
$installer->startSetup();
$installer->run("
CREATE TABLE IF NOT EXISTS `{$this->getTable('marketplace_orders')}` (
  `id` int(11) NOT NULL auto_increment,
  `order_id` int(11) NOT NULL DEFAULT '0',
  `item_ids` varchar(2048) NOT NULL DEFAULT '',
  `seller_id` int(11) NOT NULL DEFAULT '0',
  `shipment_id` int(11) NOT NULL DEFAULT '0',
  `invoice_id` int(11) NOT NULL DEFAULT '0',
  `creditmemo_id` int(11) NOT NULL DEFAULT '0',
  `is_canceled` int(2) NOT NULL DEFAULT '0',
  `shipping_charges` float(12,4) NOT NULL,
  `carrier_name` varchar(255) NOT NULL,
  `tracking_number` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

");

$prefix = Mage::getConfig()->getTablePrefix();

$connection = $this->getConnection();
/**
 * Update tables 'marketplace_saleslist'
 */
$connection->addColumn($prefix.'marketplace_saleslist', 'parent_item_id', 'int(11) NULL');
$connection->addColumn($prefix.'marketplace_saleslist', 'order_item_id', 'int(11)  NOT NULL DEFAULT  0');
/**
 * Update tables 'marketplace_userdata'
 */
$connection->addColumn($prefix.'marketplace_userdata', 'others_info', 'text NOT NULL');

$connection->addColumn($prefix.'marketplace_userdata', 'gplus_id', 'varchar(255) NOT NULL');
$connection->addColumn($prefix.'marketplace_userdata', 'youtube_id', 'varchar(255) NOT NULL');
$connection->addColumn($prefix.'marketplace_userdata', 'vimeo_id', 'varchar(255) NOT NULL');
$connection->addColumn($prefix.'marketplace_userdata', 'instagram_id', 'varchar(255) NOT NULL');
$connection->addColumn($prefix.'marketplace_userdata', 'pinterest_id', 'varchar(255) NOT NULL');
$connection->addColumn($prefix.'marketplace_userdata', 'moleskine_id', 'varchar(255) NOT NULL');

$connection->addColumn($prefix.'marketplace_userdata', 'tw_active', 'int(2) NOT NULL DEFAULT 0');
$connection->addColumn($prefix.'marketplace_userdata', 'fb_active', 'int(2) NOT NULL DEFAULT 0');
$connection->addColumn($prefix.'marketplace_userdata', 'gplus_active', 'int(2) NOT NULL DEFAULT 0');
$connection->addColumn($prefix.'marketplace_userdata', 'youtube_active', 'int(2) NOT NULL DEFAULT 0');
$connection->addColumn($prefix.'marketplace_userdata', 'vimeo_active', 'int(2) NOT NULL DEFAULT 0');
$connection->addColumn($prefix.'marketplace_userdata', 'instagram_active', 'int(2) NOT NULL DEFAULT 0');
$connection->addColumn($prefix.'marketplace_userdata', 'pinterest_active', 'int(2) NOT NULL DEFAULT 0');
$connection->addColumn($prefix.'marketplace_userdata', 'moleskine_active', 'int(2) NOT NULL DEFAULT 0');

$installer->endSetup(); 
