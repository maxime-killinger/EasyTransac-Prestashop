<?php

/**
 * EasyTransac spinner controller.
 */
class EasyTransacSpinnerModuleFrontController extends ModuleFrontController
{

	public $display_column_left = false;

	public function initContent()
	{
		$this->module->loginit();
		EasyTransac\Core\Logger::getInstance()->write('Start Spinner');
		
		parent::initContent();
		EasyTransac\Core\Logger::getInstance()->write('Spinner context cookie cart: ' . $this->context->cookie->cart_id);
		$existing_order_id = OrderCore::getOrderByCartId($this->context->cookie->cart_id);
		EasyTransac\Core\Logger::getInstance()->write('Spinner context order id: ' . $existing_order_id);

		$existing_order = new Order($existing_order_id);

		// Check if order_id and current_states are not empty.
		// When an order is initialized, it has still a current state 0.
		if (!empty($existing_order_id && !empty($existing_order->current_state)))
		{
			# EasyTransac API returned and validated order.
			Tools::redirect(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/validation');
			return;
		}

		$this->setTemplate('module:easytransac/views/templates/front/spinner.tpl');	
	}

	public function postProcess()
	{
		$this->context->smarty->assign(array(
			'isPending' => 1,
			'isCanceled' => 0,
			'isAccepted' => 0,
		));
	}

}
