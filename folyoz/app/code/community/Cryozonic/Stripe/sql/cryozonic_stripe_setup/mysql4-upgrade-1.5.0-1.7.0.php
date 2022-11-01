<?php

$installer = $this;

$installer->startSetup();

try {
    $installer->run("
    alter table cryozonic_stripesubscriptions_customers add column customer_email varchar(255) null
    ");
}
catch (\Exception $e) {}

$installer->endSetup();