<?php

/**
 * EasyTransac API class.
 *
 * v1.1 - Support non HTTPS websites, verbose error log, system requirements, PHP <5.5 fix.
 * v1.2 - FIX - Payment cancellation on HTTP sites did validate orders. Now the user is redirected to the payment methods choice instead.
 * v1.3 - FIX - Empty birthdates instead of 0000-00-00 which isn't supported by the API
 * v1.4 - FIX - Cart validation fix and new pending state for EasyTransac payment.
 * v1.5 - UPD - Order only created after payment (success or fail).
 * v1.6 - FIX - Order update works now.
 *        UPD - Captured payment can only be updated to refunded.
 *        REV - Revert of v1.4 : when launching EasyTransac payment, the card is set to pending.
 * v1.7 - REV v1.6 REV (UPD and FIX retained)
 *        NEW - Checks total price paid and cart total and errors if they don't match.
 *        NEW - HTTPS ONLY : Validation/Notification lock to not double orders.
 * v1.9 - UPD - cURL fallback.
 * v1.91 - NEW - OneClick.
 */
class EasyTransacApi
{
	/**
	 * Available services.
	 */
	const SERVICE_PAYMENT = 'api/payment/page';
	const SERVICE_LISTCARDS = 'api/payment/listcards';
	const SERVICE_ONECLICK = 'api/payment/oneclick';

	protected $selected_service;
	
		/**
	 * Sets service to payment.
	 */
	public function setServicePayment()
	{
		$this->selected_service = self::SERVICE_PAYMENT;
		return $this;
	}

	/**
	 * Sets service to listcards.
	 */
	public function setServiceListCards()
	{
		$this->selected_service = self::SERVICE_LISTCARDS;
		return $this;
	}

	/**
	 * Sets service to oneclick.
	 */
	public function setServiceOneClick()
	{
		$this->selected_service = self::SERVICE_ONECLICK;
		return $this;
	}

	/**
	 * Communicates with EasyTransac.
	 * @param array $api_key	API key.
	 * @param array $data		Data payload.
	 * @return type				EasyTransac response.
	 */
	public function communicate($api_key, $data)
	{
		if ($this->selected_service === null)
		{
			throw new \Exception('EasyTransac no service set');
		}

		$data['Signature'] = EasytransacApi::easytransac__get_signature($data, $api_key);

		// Call EasyTransac API to initialize a transaction.
		if (function_exists('curl_version'))
		{
			$curl = curl_init();
			$curl_header = array('EASYTRANSAC-API-KEY:' . $api_key);

			// Add additional headers
			if ($this->selected_service === self::SERVICE_LISTCARDS)
			{
				$curl_header[] = 'EASYTRANSAC-LISTCARDS:1';
			}
			elseif ($this->selected_service === self::SERVICE_ONECLICK)
			{
				$curl_header[] = 'EASYTRANSAC-ONECLICK:1';
			}

			curl_setopt($curl, CURLOPT_HTTPHEADER, $curl_header);
			curl_setopt($curl, CURLOPT_POST, TRUE);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

			if (defined('CURL_SSLVERSION_TLSv1_2'))
			{
				$cur_url = 'https://www.easytransac.com/' . $this->selected_service;
			}
			else
			{
				$cur_url = 'https://gateway.easytransac.com';
			}
			curl_setopt($curl, CURLOPT_URL, $cur_url);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
			$et_return = curl_exec($curl);
			if (curl_errno($curl))
			{
				throw new \Exception('EasyTransac cURL Error: ' . curl_error($curl));
			}
			curl_close($curl);
			$et_return = json_decode($et_return, TRUE);
		}
		else
		{
			$opts = array(
				'http' => array(
					'method' => 'POST',
					'header' => "Content-type: application/x-www-form-urlencoded\r\n"
					. "EASYTRANSAC-API-KEY:" . $api_key . "\r\n",
					'content' => http_build_query($data),
					'timeout' => 5,
				),
			);

			// Add additional headers
			if ($this->selected_service === self::SERVICE_LISTCARDS)
			{
				$opts['http']['header'] .= 'EASYTRANSAC-LISTCARDS:1' . "\r\n";
			}
			elseif ($this->selected_service === self::SERVICE_ONECLICK)
			{
				$opts['http']['header'] .= 'EASYTRANSAC-ONECLICK:1' . "\r\n";
			}

			$context = stream_context_create($opts);
			$et_return = file_get_contents('https://gateway.easytransac.com', FALSE, $context);
			$et_return = json_decode($et_return, TRUE);
		}

		return $et_return;
	}
	
	/**
	 * Requests a transaction page.
	 * @param array $data
	 * @param type $api_key
	 * @return type
	 */
	function easytransac_payment_page(&$data, $api_key)
	{
		try
		{
			$data['Signature'] = $this->easytransac__get_signature($data, $api_key);

			// Curl call EasyTransac API to initialize a transaction.
			if (function_exists('curl_version')) {
				$curl			 = curl_init();
				$curl_header		 = 'EASYTRANSAC-API-KEY:' . $api_key;
				curl_setopt($curl, CURLOPT_HTTPHEADER, array($curl_header));
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				if (defined('CURL_SSLVERSION_TLSv1_2')) {
					$cur_url = 'https://www.easytransac.com/api/payment/page';
				}
				else {
					$cur_url = 'https://gateway.easytransac.com';
				}
				curl_setopt($curl, CURLOPT_URL, $cur_url);
				curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
				$et_return		 = curl_exec($curl);
				$curl_errno		 = curl_errno($curl);
				$curl_error_message	 = curl_errno($curl) . ' - ' . curl_error($curl);
				curl_close($curl);
				$et_return		 = json_decode($et_return, true);
			}
			else {
				// file_get_contents() method
				$opts = array(
				  'http' => array(
					'method' => 'POST',
					'header' => "Content-type: application/x-www-form-urlencoded\r\n"
					. "EASYTRANSAC-API-KEY:" . $api_key . "\r\n",
					'content' => http_build_query($data),
					'timeout' => 5,
				  ),
				);
				$context   = stream_context_create($opts);
				$et_return = file_get_contents('https://gateway.easytransac.com', FALSE, $context);
				$et_return = json_decode($et_return, TRUE);
			}
		}
		catch (Exception $e)
		{
			$message = 'EasyTransac : Exception: ' . $e->getMessage();
			Logger::AddLog($message, 3);
			return;
			
		}

		if (!empty($et_return['Error']))
		{
			$message = 'EasyTransac : Error: ' . $et_return['Error'];
			Logger::AddLog($message, 3);
			return;
		}

		if (!empty($curl_errno))
		{
			$message = 'EasyTransac : cURL error: ' . $curl_error_message;
			Logger::AddLog($message, 3);
			return;
		}
		
		if (empty($et_return))
		{
			$message = 'EasyTransac : empty payement page response';
			Logger::AddLog($message, 3);
			return;
		}

		return $et_return;
	}

	/**
	 * Easytransac API key signature.
	 * @param array		$params
	 * @param string	$apiKey
	 * @return string
	 */
	private function easytransac__get_signature($params, $apiKey)
	{
		$signature = '';
		if (is_array($params))
		{
			ksort($params);
			foreach ($params as $name => $valeur)
			{
				if (strcasecmp($name, 'signature') != 0)
				{
					if (is_array($valeur))
					{
						ksort($valeur);
						foreach ($valeur as $v)
							$signature .= $v . '$';
					}
					else
					{
						$signature .= $valeur . '$';
					}
				}
			}
		}
		else
		{
			$signature = $params . '$';
		}

		$signature .= $apiKey;
		return sha1($signature);
	}

	/**
	 * Check the signature of EasyTransac's incoming data.
	 * @param type $data		ET received data.
	 * @param type $api_key		ET API key.
	 * @return bool			True if the signature is valid.
	 */
	public function easytransac__verify_signature($data, $api_key)
	{
		$signature	 = $data['Signature'];
		unset($data['Signature']);
		$calculated	 = $this->easytransac__get_signature($data, $api_key);
		return $signature === $calculated;
	}

	/**
	 * Get the client ip Address.
	 * @return string
	 */
	function get_client_ip()
	{
		$ipaddress	 = '';
		if (isset($_SERVER['HTTP_CLIENT_IP']))
			$ipaddress	 = $_SERVER['HTTP_CLIENT_IP'];
		elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
			$ipaddress	 = $_SERVER['HTTP_X_FORWARDED_FOR'];
		elseif (isset($_SERVER['HTTP_X_FORWARDED']))
			$ipaddress	 = $_SERVER['HTTP_X_FORWARDED'];
		elseif (isset($_SERVER['HTTP_FORWARDED_FOR']))
			$ipaddress	 = $_SERVER['HTTP_FORWARDED_FOR'];
		elseif (isset($_SERVER['HTTP_FORWARDED']))
			$ipaddress	 = $_SERVER['HTTP_FORWARDED'];
		elseif (isset($_SERVER['REMOTE_ADDR']))
			$ipaddress	 = $_SERVER['REMOTE_ADDR'];
		else
			$ipaddress	 = 'UNKNOWN';
		return $ipaddress;
	}
	
	function get_server_info_string()
	{
		$curl_info_string	 = function_exists('curl_version') ? 'enabled' : 'not found';
		$openssl_info_string = OPENSSL_VERSION_NUMBER >= 0x10001000 ? 'TLSv1.2' : 'OpenSSL version deprecated';
		$https_info_string = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'S' : '';
		return sprintf('Prestashop 1.91 [cURL %s, OpenSSL %s, HTTP%s]', $curl_info_string, $openssl_info_string, $https_info_string);
	}

}
