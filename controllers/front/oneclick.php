<?php

/**
 * EasyTransac oneclick controller.
 */
class EasyTransacOneClickModuleFrontController extends ModuleFrontController
{

	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		if (!$this->context->customer->id || empty($_POST['Alias'])
				|| !$this->context->cart->id)
			die;
		$debug = 0;
		$dump_path = __DIR__ . '/dump';
		include_once(_PS_MODULE_DIR_ . 'easytransac/api.php');
		$easytransac_api = new EasyTransacApi();
		//////////////
		$api_key = Configuration::get('EASYTRANSAC_API_KEY');
		$total = 100 * $this->context->cart->getOrderTotal(true, Cart::BOTH);
		$send_data = array(
			"Alias" => strip_tags($_POST['Alias']),
			"Amount" => $total,
			"ClientIp" => $easytransac_api->get_client_ip(),
			"OrderId" => $this->context->cart->id,
			"ClientId" => $this->context->customer->getClient_id(),
			"UserAgent" => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
		);

		// EasyTransac communication.
		$et_return = $easytransac_api
				->setServiceOneClick()
				->communicate($api_key, $send_data);

		if (!empty($et_return['Error']))
		{
			echo json_encode(array(
				'error' => 'yes', 'message' => $et_return['Error']
			));
			return;
		}

		/**
		 * Response process $et_return['Result']
		 */
		$data = $et_return['Result'];
		
		if (!(Configuration::get('EASYTRANSAC_ID_ORDER_STATE') > 0))
		{
			// Duplicate from  create_et_order_state()
			// for sites upgrading from older version
			$OrderState = new OrderState();
			$OrderState->id = EASYTRANSAC_STATE_ID;
			$OrderState->name = array_fill(0, 10, "EasyTransac payment pending");
			$OrderState->send_email = 0;
			$OrderState->invoice = 0;
			$OrderState->color = "#ff9900";
			$OrderState->unremovable = false;
			$OrderState->logable = 0;
			$OrderState->add();
			Configuration::updateValue('EASYTRANSAC_ID_ORDER_STATE', $OrderState->id);
		}

		$cart = new Cart($data['OrderId']);

		if (empty($cart->id))
		{
			Logger::AddLog('EasyTransac:OneClick: Unknown Cart id');
			die;
		}
		$customer = new Customer($cart->id_customer);

		$existing_order_id = OrderCore::getOrderByCartId($data['OrderId']);
		$existing_order = new Order($existing_order_id);

		if ($debug)
		{
			$debug_string = 'OneClick customer : ' . $existing_order->id_customer;
			file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
		}

		$payment_status = null;

		$payment_message = $data['Message'];

		if (!empty($data['Error']))
			$payment_message .= '.' . $data['Error'];

		if (!empty($data['AdditionalError']) && is_array($data['AdditionalError']))
			$payment_message .= ' ' . implode(' ', $data['AdditionalError']);

		// 2: payment accepted, 6: canceled, 7: refunded, 8: payment error
		switch ($data['Status'])
		{
			case 'captured':
				$payment_status = 2;
				break;

			case 'pending':
				$payment_status = (int) Configuration::get('EASYTRANSAC_ID_ORDER_STATE');
				break;

			case 'refunded':
				$payment_status = 7;
				break;

			case 'failed':
			default :
				$payment_status = 8;
				break;
		}

		if ($debug)
		{
			$debug_string = 'OneClick for OrderId : ' . $data['OrderId'] . ', Status: ' . $data['Status'] . ', Prestashop Status: ' . $payment_status;
			file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
		}


		// Check that paid amount matches cart total (v1.7)
		# String string format compare
		$paid_total = number_format($data['Amount'], 2, '.', '');
		$cart_total = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
		$amount_match = $paid_total === $cart_total;

		if ($debug)
		{
			$debug_string = 'OneClick Paid total: ' . $paid_total . ' prestashop price: ' . $cart_total;
			file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
		}

		if (!$amount_match && 2 == $payment_status)
		{
			$payment_message = $this->module->l('Price paid on EasyTransac is not the same that on Prestashop - Transaction : ') . $data['Tid'];
			$payment_status = 8;

			if ($debug)
			{
				$debug_string = 'OneClick Amount mismatch';
				file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
			}
		}


		// Creating Order
		$total_paid_float = (float) $paid_total;

		if ($debug)
		{
			$debug_string = 'OneClick Total paid float: ' . $total_paid_float;
			file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
		}

		if('failed' != $et_return['Result']['Status']) {
			$this->module->validateOrder($cart->id, $payment_status, $total_paid_float, $this->module->displayName, $payment_message, $mailVars = array(), null, false, $customer->secure_key);
		}
		if ($debug)
		{
			$debug_string = 'OneClick Order validated';
			file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
		}



		// AJAX Output
		$json_status_output = '';
		switch ($et_return['Result']['Status'])
		{
			case 'captured':
			case 'pending':
				$json_status_output = 'processed';
				break;

			default:
				$json_status_output = 'failed';
				break;
		}

		if (!empty($et_return['Result']['Error']))
		{
			echo json_encode(array(
				'error' => 'yes', 'message' => $et_return['Result']['Error']
			));
		}
		else
		{
			$next_hop = sprintf('%s/index.php?controller=order-confirmation&id_cart=%d&id_module=%d&id_order=%d&key=%s', 
					Tools::getShopDomainSsl(true, true),
					$this->context->cookie->cart_id,
					$this->module->id,
					$this->module->currentOrder,
					$this->context->customer->secure_key);
			echo json_encode(array(
				'paid_status' => $json_status_output,
				'error' => 'no',
				'redirect_page' => $next_hop,
			));
		}
		die;
	}
}
