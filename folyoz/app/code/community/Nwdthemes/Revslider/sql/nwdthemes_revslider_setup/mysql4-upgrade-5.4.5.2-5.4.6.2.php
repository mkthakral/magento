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

ALTER TABLE `{$this->getTable('nwdrevslider/css')}`
CHANGE `params` `params` longtext NOT NULL AFTER `hover`;

ALTER TABLE `{$this->getTable('nwdrevslider/navigations')}`
CHANGE `css` `css` longtext NOT NULL AFTER `handle`,
CHANGE `markup` `markup` longtext NOT NULL AFTER `css`,
CHANGE `settings` `settings` longtext NULL AFTER `markup`;

ALTER TABLE `{$this->getTable('nwdrevslider/sliders')}`
CHANGE `settings` `settings` text NULL DEFAULT '' AFTER `params`;

";

$installer = $this;
$installer->startSetup();
$installer->run($upgradeSQL);
$installer->endSetup();
