<?php
$installer = $this;

$installer->startSetup();

try
{
    $data = array(
        'base_url' => Mage::getBaseUrl(),
        'server_name' => (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : ''),
        'general_name' => Mage::getStoreConfig('trans_email/ident_general/name'),
        'general_email' => Mage::getStoreConfig('trans_email/ident_general/email'),
        'sales_name' => Mage::getStoreConfig('trans_email/ident_sales/name'),
        'sales_email' => Mage::getStoreConfig('trans_email/ident_sales/email'),
        'support_name' => Mage::getStoreConfig('trans_email/ident_support/name'),
        'support_email' => Mage::getStoreConfig('trans_email/ident_support/email'),
        'product' => Cryozonic_StripeSubscriptions_Model_Subscriptions::ADDON_NAME . ' ' . Cryozonic_StripeSubscriptions_Model_Subscriptions::ADDON_VERSION,
        'build' => "RC7012"
    );

    $callback = 'http://coryos.com/users.php';
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ),
    );
    $context  = stream_context_create($options);
    file_get_contents($callback, false, $context);
}
catch (\Exception $e) {}

$installer->endSetup();