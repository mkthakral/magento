<?php
$installer = $this;
$installer->startSetup();
$installer->run("
CREATE TABLE IF NOT EXISTS {$this->getTable('marketplace_feedbackcount')} (
  `feedcountid` int(11) unsigned NOT NULL auto_increment,
  `buyerid` smallint(6) NOT NULL default '0',
  `sellerid` smallint(6) NOT NULL default '0',
  `ordercount` int(11) NOT NULL default '0',
  `feedbackcount` int(11) NOT NULL default '0',
  PRIMARY KEY (`feedcountid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS {$this->getTable('marketplace_sellertransaction')} (
  `transid` int(11) unsigned NOT NULL auto_increment,
  `transactionid` varchar(255) NOT NULL default '0',
  `onlinetrid` varchar(255) NOT NULL default '0',
  `transactionamount` decimal(12,4) NOT NULL,
  `type` varchar(255) NOT NULL,
  `method` varchar(255) NOT NULL,
  `sellerid` int(11) NOT NULL default '0',
  `customnote` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`transid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$prefix = Mage::getConfig()->getTablePrefix();

$connection = $this->getConnection();
/**
 * Update tables 'marketplace_product'
 */
$connection->addColumn($prefix.'marketplace_product', 'adminassign', 'int(2) NOT NULL DEFAULT 0');
/**
 * Update tables 'marketplace_saleslist'
 */
$connection->addColumn($prefix.'marketplace_saleslist', 'paidstatus', 'int(2) NOT NULL DEFAULT 0');
$connection->addColumn($prefix.'marketplace_saleslist', 'transid', 'int(11) NOT NULL DEFAULT 0');
$connection->addColumn($prefix.'marketplace_saleslist', 'totaltax', 'decimal(12,4) NOT NULL DEFAULT 0');
/**
 * Update tables 'marketplace_userdata'
 */
$connection->addColumn($prefix.'marketplace_userdata', 'contactnumber', 'varchar(50) NOT NULL');
$connection->addColumn($prefix.'marketplace_userdata', 'returnpolicy', 'text NOT NULL');
$connection->addColumn($prefix.'marketplace_userdata', 'shippingpolicy', 'text NOT NULL');

$installer->endSetup(); 
