<?php

class EasyTransacValidationModuleFrontController extends ModuleFrontController
{

	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		$this->module->loginit();
		EasyTransac\Core\Logger::getInstance()->write('Start Validation');
		EasyTransac\Core\Logger::getInstance()->write("\n\n" . var_export($_POST, true), FILE_APPEND);

		$https_enabled = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
		EasyTransac\Core\Logger::getInstance()->write('Validation order : ' . $this->context->cookie->order_id);
		$cart = new Cart($this->context->cookie->cart_id);

		if (empty($cart->id))
		{
			Logger::AddLog('EasyTransac: Unknown Cart id');
			Tools::redirect('index.php?controller=order&step=1');
		}

		$existing_order_id = OrderCore::getOrderByCartId($this->context->cookie->cart_id);

		$existing_order = new Order($existing_order_id);

		$this->module->create_easytransac_order_state();

		// Pending status if HTTPS is not used.
		if (!$https_enabled)
		{
			// HTTP Only
			EasyTransac\Core\Logger::getInstance()->write('Validation HTTP');
			// Redirect to validation page.
			if (empty($existing_order->id) || empty($existing_order->current_state))
			{
				Tools::redirect(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/spinner');
			}
			else
			{
				Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $this->context->cookie->cart_id . '&id_module=' . $this->module->id . '&id_order=' . $existing_order->id . '&nohttps=1' . '&key=' . $this->context->customer->secure_key);
			}
			return;
		}
		EasyTransac\Core\Logger::getInstance()->write('Validation HTTPS');

		// If HTTPS is used, POST data must be present.
		if (empty($_POST))
		{
			Logger::AddLog('EasyTransac: Invalid data received');
			Tools::redirect('index.php?controller=order&step=1');
			return;
		}

		// SDK for HTTPS answer.
		try
		{
			$response = \EasyTransac\Core\PaymentNotification::getContent($_POST, Configuration::get('EASYTRANSAC_API_KEY'));

			if (!$response)
				throw new Exception('empty response');
		}
		catch (Exception $exc)
		{
			error_log('EasyTransac error: ' . $exc->getMessage());
			Logger::AddLog('EasyTransac:' . $exc->getMessage());
			EasyTransac\Core\Logger::getInstance()->write('Exception: ' . $exc->getMessage());
			Tools::redirect('index.php?controller=order&step=1');
			die;
		}

		// Lock so that notification don't interfere
		if ($this->module->isLocked($response->getOrderId()))
		{
			EasyTransac\Core\Logger::getInstance()->write('Validation lock loop : ' . $response->getOrderId());
			// Wait loop (max 10s) to let the notification finish
			$max = 10;
			$i = 0;
			while ($i < $max)
			{
				sleep(1);
				if (!$this->module->isLocked($response->getOrderId()))
					break;
				$i++;
			}
		}

		// Lock
		$this->module->lock($response->getOrderId());

		EasyTransac\Core\Logger::getInstance()->write('Validation locked : ' . $response->getOrderId());
		EasyTransac\Core\Logger::getInstance()->write('Validation for OrderId : ' . $response->getOrderId() . ', Status: ' . $response->getStatus());

		$payment_status = null;

		$payment_message = $response->getMessage();

		if ($response->getError())
			$payment_message .= '.' . $response->getError();

		$error2 = $response->getAdditionalError();
		if (!empty($error2) && is_array($error2))
		{
			$payment_message .= ' ' . implode(' ', $error2);
		}

		// 2: payment accepted, 6: canceled, 7: refunded, 8: payment error, 12: remote payment accepted
		switch ($response->getStatus())
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

		// Check that paid amount matches cart total.
		$paid_total = number_format($response->getAmount(), 2, '.', '');
		$cart_total = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
		$amount_match = $paid_total === $cart_total;

		EasyTransac\Core\Logger::getInstance()->write('Validation Paid total: ' . $paid_total . ' prestashop price: ' . $cart_total);

		if (!$amount_match && 2 == $payment_status)
		{
			$payment_message = $this->module->l('Price paid on EasyTransac is not the same that on Prestashop - Transaction : ') . $response->getTid();
			$payment_status = 8;
			EasyTransac\Core\Logger::getInstance()->write('Validation Amount mismatch');
		}

		if (empty($existing_order->id))
		{
			$total_paid_float = (float) $paid_total;
			# Validate cart to order.
			$this->module->validateOrder($this->context->cart->id, (int) $payment_status, (float) $total_paid_float, $this->module->displayName, "", $mailVars = array(), null, false, $this->context->customer->secure_key);
			EasyTransac\Core\Logger::getInstance()->write('Validation Order validated');
			$order_id = $this->module->currentOrder;
		}
		else
		{
			$order_id = $existing_order->id;
		}

		if (((int) $existing_order->current_state != 2 || (int) $payment_status == 7) && (int) $existing_order->current_state != (int) $payment_status)
		{
			// Updating the order's state only if current state is not captured
			// or if target state is refunded
			// Updating the order's state
			$existing_order->setCurrentState($payment_status);

			// Adds message.
			if (isset($payment_message) && !empty($payment_message))
			{
				$msg = new Message();
				$message = strip_tags($payment_message, '<br>');
				$msg->message = $message;
				$msg->id_order = intval($existing_order->id);
				$msg->private = 1;
				$msg->add();
			}
			$order_id = $existing_order->id;
			EasyTransac\Core\Logger::getInstance()->write('Validation change state to: ' . $payment_status);
		}
		elseif (empty($existing_order->id) && empty($existing_order->current_state))
		{
			$this->module->unlock($response->getOrderId());

			# Currently processed by notification.
			Tools::redirect(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/spinner');
			return;
		}
		else
		{
			EasyTransac\Core\Logger::getInstance()->write('order exists and no state change');
		}
		EasyTransac\Core\Logger::getInstance()->write('Validation End of Script');
		$this->module->unlock($response->getOrderId());
		Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $this->context->cookie->cart_id . '&id_module=' . $this->module->id . '&id_order=' . $order_id . '&key=' . $this->context->customer->secure_key);
	}

}
