<?php
$installer = $this;
$installer->startSetup();

try {
    $installer->run("INSERT INTO `{$this->getTable('admin/permission_block')}` ( `block_name`, `is_allowed`) VALUES ( 'nwdrevslider/revslider', 1);");
}
catch(Exception $e) {
    Mage::log('NWD Revslider Install: '.$e->getMessage());
}

$installer->endSetup();
