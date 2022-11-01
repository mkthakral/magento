<?php

$installer = $this;
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$installer->startSetup();

$setup->addAttributeGroup(Mage_Catalog_Model_Product::ENTITY, 'Default', 'Stripe Subscriptions', 5);

$setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'cryozonic_configurable_input', array(
    'group'         => 'Stripe Subscriptions',
    'input'         => 'select',
    'type'          => 'text',
    'label'         => 'Configurable Product Input',
    'backend'       => '',
    'visible'       => 0,
    'required'      => 0,
    'user_defined'  => 1,
    'searchable'    => 0,
    'filterable'    => 0,
    'comparable'    => 0,
    'source'        => 'cryozonic_stripesubscriptions/source_configurable',
    'visible_on_front' => 0,
    'visible_in_advanced_search' => 0,
    'is_html_allowed_on_front' => 0,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
));

$setup->updateAttribute(Mage_Catalog_Model_Product::ENTITY, 'cryozonic_configurable_input', 'apply_to', 'configurable');

$installer->endSetup();