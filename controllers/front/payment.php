<?php

/**
 * EasyTransac checkout page handler.
 */
class EasyTransacPaymentModuleFrontController extends ModuleFrontController
{

	public $ssl			 = true;
	public $display_column_left	 = false;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();
		
		include_once(_PS_MODULE_DIR_ . 'easytransac/api.php');

		$api		 = new EasyTransacApi();
		$cart		 = $this->context->cart;
		$customer	 = $this->context->customer;
		$user_address	 = new Address(intval($cart->id_address_invoice));
		$api_key	 = Configuration::get('EASYTRANSAC_API_KEY');

		$total = 100 * $cart->getOrderTotal(true, Cart::BOTH);

		$langcode = $this->context->language->iso_code == 'fr' ? 'FRE' : 'ENG';
		$payment_data = array(
			"Amount"	 => $total,
			"ClientIp"	 => $api->get_client_ip(),
			"Email"		 => $customer->email,
			"OrderId"	 => $this->context->cart->id,
			"Uid"		 => $user_address->id_customer,
			"ReturnUrl"	 => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/validation',
			"CancelUrl"	 => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?controller=order&step=3',
			"3DS"		 => Configuration::get('EASYTRANSAC_3DSECURE') ? 'yes' : 'no',
			"Firstname"	 => $customer->firstname,
			"Lastname"	 => $customer->lastname,
			"Address"	 => $user_address->address1 . ' - ' . $user_address->address2,
			"ZipCode"	 => $user_address->postcode,
			"City"		 => $user_address->city,
			"BirthDate"	 => $customer->birthday == '0000-00-00' ? '' : $customer->birthday,
			"Nationality"	 => "",
			"CallingCode"	 => "",
			"Phone"		 => $user_address->phone,
			"UserAgent" => htmlspecialchars($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
			"Version" => $api->get_server_info_string(),
			"Language" => $langcode,
		);
		
		// Store cart_id in session
		$this->context->cookie->cart_id = $this->context->cart->id;
                
		$this->context->cookie->order_total = $cart->getOrderTotal(true, Cart::BOTH);

		$payment_page = $api->easytransac_payment_page($payment_data, $api_key);

		$this->context->smarty->assign(array(
			'nbProducts'	 => $cart->nbProducts(),
			'total'		 => $cart->getOrderTotal(true, Cart::BOTH),
			'payment_page'	 => $payment_page,
			'payment_data'	 => $payment_data,
		));
		
		// Duplicate from  create_et_order_state()
		// for sites upgrading from older version
		if(!(Configuration::get('EASYTRANSAC_ID_ORDER_STATE') > 0)) 
		{
			$OrderState = new OrderState();
			$OrderState->id = EASYTRANSAC_STATE_ID;
			$OrderState->name = array_fill(0,10,"EasyTransac payment pending");
			$OrderState->send_email = 0;
			$OrderState->invoice = 0;
			$OrderState->color = "#ff9900";
			$OrderState->unremovable = false;
			$OrderState->logable = 0;
			$OrderState->add();
			Configuration::updateValue('EASYTRANSAC_ID_ORDER_STATE', $OrderState->id);
		}
		
		$this->setTemplate('payment_execution.tpl');
	}

}
