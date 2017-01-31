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

		EasyTransac\Core\Logger::getInstance()->write('Validation order cart id : ' . $this->context->cookie->order_id);
		$cart = new Cart($this->context->cookie->cart_id);

		if (empty($cart->id))
		{
			Logger::AddLog('EasyTransac: Unknown Cart id');
			Tools::redirect('index.php?controller=order&step=1');
		}

		$existing_order_id = OrderCore::getOrderByCartId($this->context->cookie->cart_id);

		$existing_order = new Order($existing_order_id);

		EasyTransac\Core\Logger::getInstance()->write('Validation order id from cart : ' . $existing_order_id);
		EasyTransac\Core\Logger::getInstance()->write('Validation customer : ' . $existing_order->id_customer);

		$this->module->create_easytransac_order_state();

		/**
		 * Version 2.1 : spinner forced, update via notification only.
		 */
		// HTTP Only
		EasyTransac\Core\Logger::getInstance()->write('Validation redirect');
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

}
