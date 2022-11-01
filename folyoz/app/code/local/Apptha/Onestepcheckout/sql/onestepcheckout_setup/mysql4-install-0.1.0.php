<?php

$installer = $this;

$installer->startSetup();

$installer->run("INSERT INTO {$this->getTable('core_config_data')} (`path`, `value`) VALUES ('onestepcheckout/general/geoip_database', 'GeoIp/GeoLiteCity.dat')");
  

$installer->endSetup(); 