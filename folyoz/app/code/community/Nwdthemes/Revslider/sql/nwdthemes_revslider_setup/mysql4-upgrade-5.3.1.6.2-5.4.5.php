<?php

/**
 * Nwdthemes Revolution Slider Extension
 *
 * @package     Revslider
 * @author		Nwdthemes <mail@nwdthemes.com>
 * @link		http://nwdthemes.com/
 * @copyright   Copyright (c) 2015. Nwdthemes
 * @license     http://themeforest.net/licenses/terms/regular
 */

$connConfig = $this->getConnection()->getConfig();
$dbName = $connConfig['dbname'];

$upgradeSQL = "

SELECT IF (
    exists(
        SELECT DISTINCT index_name FROM information_schema.statistics 
        WHERE table_schema = '{$dbName}' 
        AND TABLE_NAME = '{$this->getTable('nwdrevslider/backup')}' AND index_name LIKE 'slide_id'
    )
    ,'SELECT ''INDEX slide_id exists'' _______;'
    ,'CREATE INDEX slide_id ON {$this->getTable('nwdrevslider/backup')}(slide_id)') INTO @a;
PREPARE stmt1 FROM @a;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SELECT IF (
    exists(
        SELECT DISTINCT index_name FROM information_schema.statistics 
        WHERE table_schema = '{$dbName}' 
        AND TABLE_NAME = '{$this->getTable('nwdrevslider/backup')}' AND index_name LIKE 'slider_id'
    )
    ,'SELECT ''INDEX slider_id exists'' _______;'
    ,'CREATE INDEX slider_id ON {$this->getTable('nwdrevslider/backup')}(slider_id)') INTO @a;
PREPARE stmt1 FROM @a;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SELECT IF (
    exists(
        SELECT DISTINCT index_name FROM information_schema.statistics 
        WHERE table_schema = '{$dbName}' 
        AND TABLE_NAME = '{$this->getTable('nwdrevslider/navigations')}' AND index_name LIKE 'handle'
    )
    ,'SELECT ''INDEX handle exists'' _______;'
    ,'CREATE INDEX handle ON {$this->getTable('nwdrevslider/navigations')}(handle)') INTO @a;
PREPARE stmt1 FROM @a;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SELECT IF (
    exists(
        SELECT DISTINCT index_name FROM information_schema.statistics 
        WHERE table_schema = '{$dbName}' 
        AND TABLE_NAME = '{$this->getTable('nwdrevslider/options')}' AND index_name LIKE 'handle'
    )
    ,'SELECT ''INDEX handle exists'' _______;'
    ,'CREATE INDEX handle ON {$this->getTable('nwdrevslider/options')}(handle)') INTO @a;
PREPARE stmt1 FROM @a;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SELECT IF (
    exists(
        SELECT DISTINCT index_name FROM information_schema.statistics 
        WHERE table_schema = '{$dbName}' 
        AND TABLE_NAME = '{$this->getTable('nwdrevslider/slides')}' AND index_name LIKE 'slider_id'
    )
    ,'SELECT ''INDEX slider_id exists'' _______;'
    ,'CREATE INDEX slider_id ON {$this->getTable('nwdrevslider/slides')}(slider_id)') INTO @a;
PREPARE stmt1 FROM @a;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SELECT IF (
    exists(
        SELECT DISTINCT index_name FROM information_schema.statistics 
        WHERE table_schema = '{$dbName}' 
        AND TABLE_NAME = '{$this->getTable('nwdrevslider/static')}' AND index_name LIKE 'slider_id'
    )
    ,'SELECT ''INDEX slider_id exists'' _______;'
    ,'CREATE INDEX slider_id ON {$this->getTable('nwdrevslider/static')}(slider_id)') INTO @a;
PREPARE stmt1 FROM @a;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

";

$installer = $this;
$installer->startSetup();
$installer->run($upgradeSQL);
$installer->endSetup();
