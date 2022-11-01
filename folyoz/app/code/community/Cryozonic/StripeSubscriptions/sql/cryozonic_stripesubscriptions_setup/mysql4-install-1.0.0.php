<?php
$installer = $this;

$installer->startSetup();

if (!$installer->tableExists('cryozonic_stripesubscriptions_customers')) {

	$installer->run("

	CREATE TABLE cryozonic_stripesubscriptions_customers (
	  `id` int(11) unsigned NOT NULL auto_increment,
	  `customer_id` int(11) unsigned NOT NULL,
	  `stripe_id` varchar(255) NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

}

if (!$installer->tableExists('cryozonic_stripesubscriptions_plans')) {

	$installer->run("

	CREATE TABLE cryozonic_stripesubscriptions_plans (
	  `id` int(11) unsigned NOT NULL auto_increment,
	  `product_id` int(11) unsigned NOT NULL,
	  `stripe_id` varchar(50) NOT NULL,
	  `name` varchar(1024) NOT NULL,
	  `amount` int(11) unsigned NOT NULL,
	  `currency` varchar(3) NOT NULL,
	  `interval` varchar(5) NOT NULL,
	  `interval_count` int(11) unsigned NOT NULL,
	  `trial_period_days` int(11) unsigned NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

}

$installer->endSetup();