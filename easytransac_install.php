<?php

if (!defined('_PS_VERSION_'))
	exit;

/**
 * Prestashop installation class.
 */
class EasyTransacInstall
{
	/**
	 * Set configuration table
	 */
	public function updateConfiguration()
	{
		Configuration::updateValue('EASYTRANSAC_API_KEY', 0);
		Configuration::updateValue('EASYTRANSAC_3DSECURE_ONLY', 1);
		Configuration::updateValue('EASYTRANSAC_DEBUG', 0);
		Configuration::updateValue('EASYTRANSAC_ONECLICK', 0);
	}
	
	/**
	 * Delete EasyTransac configuration
	 */
	public function deleteConfiguration()
	{
		Configuration::deleteByName('EASYTRANSAC_API_KEY');
		Configuration::deleteByName('EASYTRANSAC_3DSECURE_ONLY');
	}
	
	/**
     * Create EasyTransac table
     */
    public function createTables()
    {
        if (!Db::getInstance()->Execute('
		CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'easytransac_customer` (
			`id_customer` int(10) unsigned NOT NULL,
			`client_id` VARCHAR(16) NOT NULL,
			PRIMARY KEY (`id_customer`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;
			ALTER TABLE `'._DB_PREFIX_.'easytransac_customer` ADD KEY `easytransac_client_id` (`client_id`);')) {
            return false;
        }
    }
	
	/**
	 * Delete EasyTransac table
	 */
	public function deleteTables()
	{
		Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'easytransac_customer`;');
	}
}
