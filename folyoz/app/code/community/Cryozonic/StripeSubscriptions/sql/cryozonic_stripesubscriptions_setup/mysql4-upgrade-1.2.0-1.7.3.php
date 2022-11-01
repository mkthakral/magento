<?php

$installer = $this;

$installer->startSetup();

if ($installer->tableExists('cryozonic_stripesubscriptions_plans'))
    $installer->run("DROP TABLE cryozonic_stripesubscriptions_plans");

$customer = Mage::getModel('customer/customer')->load(1);
if (!$customer->getId())
{
    // Subscription checkouts for guests require that a customer with an ID of 1 exists. This is normally created
    // by the initial magento sample sql file, however if it was never ran or the example customers were deleted,
    // then an SQL error will occur for guest checkouts, so we re-create a dummy customer account here.
    try
    {
        $customerEntityTable = $installer->getTable('customer/entity');

        $installer->run("INSERT INTO $customerEntityTable (entity_id, entity_type_id, email, group_id, created_at, is_active) VALUES (1, 1, 'dont-delete@cryozonic-stripe-subscriptions.com', 1, 0, 0)");
    }
    catch (\Exception $e) { }
}

$installer->endSetup();
