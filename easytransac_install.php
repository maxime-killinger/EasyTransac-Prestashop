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
	}
	
	/**
	 * Delete EasyTransac configuration
	 */
	public function deleteConfiguration()
	{
		Configuration::deleteByName('EASYTRANSAC_API_KEY');
		Configuration::deleteByName('EASYTRANSAC_3DSECURE_ONLY');
	}
}
