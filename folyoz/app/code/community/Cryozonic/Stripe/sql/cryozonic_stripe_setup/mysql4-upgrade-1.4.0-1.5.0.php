<?php

$installer = $this;

$installer->startSetup();

if (!$installer->tableExists('cryozonic_stripesubscriptions_customers')) {

    $installer->run("

    CREATE TABLE cryozonic_stripesubscriptions_customers (
      `id` int(11) unsigned NOT NULL auto_increment,
      `customer_id` int(11) unsigned NOT NULL,
      `stripe_id` varchar(255) NOT NULL,
      `last_retrieved` int NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

}
else
{
    try {
        $installer->run("
        alter table cryozonic_stripesubscriptions_customers add column last_retrieved int not null default 0
        ");
    } catch (\Exception $e) {} // Rare case when Stripe Subscriptions was installed before Stripe Payments
}

$installer->endSetup();