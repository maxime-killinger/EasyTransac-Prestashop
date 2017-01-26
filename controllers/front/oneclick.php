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
		if (!$this->context->customer->id || empty($_POST['Alias']) || !$this->context->cart->id)
			die;
		$this->module->loginit();
		EasyTransac\Core\Logger::getInstance()->write('Start Oneclick');

		$dump_path = __DIR__ . '/dump';
		$api_key = Configuration::get('EASYTRANSAC_API_KEY');
		$total = 100 * $this->context->cart->getOrderTotal(true, Cart::BOTH);
//		EasyTransac\Core\Services::getInstance()->setDebug(true);
		EasyTransac\Core\Services::getInstance()->provideAPIKey($api_key);

		// SDK OneClick
		$transaction = (new EasyTransac\Entities\OneClickTransaction())
				->setAlias(strip_tags($_POST['Alias']))
				->setAmount($total)
				->setOrderId($this->context->cart->id)
				->setClientId($this->context->customer->getClient_id());


		$dp = new EasyTransac\Requests\OneClickPayment();
		$response = $dp->execute($transaction);

		if (!$response->isSuccess())
		{
			echo json_encode(array(
				'error' => 'yes', 'message' => $response->getErrorMessage()
			));
			return;
		}

		/* @var  $doneTransaction \EasyTransac\Entities\DoneTransaction */
		$doneTransaction = $response->getContent();

		$this->module->create_easytransac_order_state();

		$cart = new Cart($doneTransaction->getOrderId());

		if (empty($cart->id))
		{
			Logger::AddLog('EasyTransac:OneClick: Unknown Cart id');
			die;
		}
		$customer = new Customer($cart->id_customer);

		$existing_order_id = OrderCore::getOrderByCartId($doneTransaction->getOrderId());
		$existing_order = new Order($existing_order_id);

		EasyTransac\Core\Logger::getInstance()->write('OneClick customer : ' . $existing_order->id_customer);

		$payment_status = null;

		$payment_message = $doneTransaction->getMessage();

		if ($doneTransaction->getError())
		{
			$payment_message .= '.' . $doneTransaction->getError();
		}

		$error2 = $doneTransaction->getAdditionalError();
		if (!empty($error2) && is_array($error2))
		{
			$payment_message .= ' ' . implode(' ', $error2);
		}

		// 2: payment accepted, 6: canceled, 7: refunded, 8: payment error
		switch ($doneTransaction->getStatus())
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

		EasyTransac\Core\Logger::getInstance()->write('OneClick for OrderId : ' . $doneTransaction->getOrderId() . ', Status: ' . $doneTransaction->getStatus() . ', Prestashop Status: ' . $payment_status);

		// Check that paid amount matches cart total (v1.7)
		# String string format compare
		$paid_total = number_format($doneTransaction->getAmount(), 2, '.', '');
		$cart_total = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
		$amount_match = $paid_total === $cart_total;


		EasyTransac\Core\Logger::getInstance()->write('OneClick Paid total: ' . $paid_total . ' prestashop price: ' . $cart_total);

		if (!$amount_match && 2 == $payment_status)
		{
			$payment_message = $this->module->l('Price paid on EasyTransac is not the same that on Prestashop - Transaction : ') . $doneTransaction->getTid();
			$payment_status = 8;
			EasyTransac\Core\Logger::getInstance()->write('OneClick Amount mismatch');
		}

		// Creating Order
		$total_paid_float = (float) $paid_total;
		EasyTransac\Core\Logger::getInstance()->write('OneClick Total paid float: ' . $total_paid_float);

		if ('failed' != $doneTransaction->getStatus())
		{
			$this->module->validateOrder($cart->id, $payment_status, $total_paid_float, $this->module->displayName, $payment_message, $mailVars = array(), null, false, $customer->secure_key);
		}
		EasyTransac\Core\Logger::getInstance()->write('OneClick Order validated');

		// AJAX Output
		$json_status_output = '';
		switch ($doneTransaction->getStatus())
		{
			case 'captured':
			case 'pending':
				$json_status_output = 'processed';
				break;

			default:
				$json_status_output = 'failed';
				break;
		}

		if ($doneTransaction->getError())
		{
			EasyTransac\Core\Logger::getInstance()->write('OneClick error:' . $doneTransaction->getError());
			echo json_encode(array(
				'error' => 'yes',
				'message' => $doneTransaction->getError()
			));
		}
		else
		{
			$next_hop = sprintf('%s/index.php?controller=order-confirmation&id_cart=%d&id_module=%d&id_order=%d&key=%s', Tools::getShopDomainSsl(true, true), $this->context->cookie->cart_id, $this->module->id, $this->module->currentOrder, $this->context->customer->secure_key);
			echo json_encode(array(
				'paid_status' => $json_status_output,
				'error' => 'no',
				'redirect_page' => $next_hop,
			));
		}
		die;
	}

}
