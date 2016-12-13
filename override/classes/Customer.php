<?php

class Customer extends CustomerCore
{

	function getClient_id()
	{
		$sql = 'SELECT client_id FROM `' . _DB_PREFIX_ . 'easytransac_customer` '
				. ' WHERE id_customer = \'' . $this->id . '\'';
		return Db::getInstance()->getValue($sql);
	}

	function setClient_id($client_id)
	{
		Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'easytransac_customer` '
				. ' VALUES(' . (int)$this->id . ',\'' . $client_id . '\')');
	}

	/**
	 * Retrieve customers by client id.
	 *
	 * @param $clientId
	 * @return array
	 */
	public static function getByClientId($clientId)
	{
		$sql = 'SELECT Customer.*
				FROM `' . _DB_PREFIX_ . 'easytransac_customer` as ETCustomer
				JOIN `' . _DB_PREFIX_ . 'customer` AS Customer ON Customer.id_customer = ETCustomer.id_customer
				WHERE ETCustomer.`client_id` = \'' . pSQL($clientId) . '\'';

		$result = Db::getInstance()->getRow($sql);
		if (!$result)
		{
			return false;
		}
		$this->id = $result['id_customer'];
		foreach ($result as $key => $value)
		{
			if (property_exists($this, $key))
			{
				$this->{$key} = $value;
			}
		}
		return $this;
	}

}
