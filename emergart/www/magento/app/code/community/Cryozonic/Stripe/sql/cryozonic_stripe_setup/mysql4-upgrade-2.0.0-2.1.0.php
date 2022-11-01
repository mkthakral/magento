<?php

$installer = $this;

$installer->startSetup();

try {
    $installer->run("alter table cryozonic_stripesubscriptions_customers add column session_id varchar(255) null");
} catch (Exception $e) {}

$installer->endSetup();