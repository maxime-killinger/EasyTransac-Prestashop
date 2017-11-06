<?php

/**
 * EasyTransac checkout page handler.
 */
class EasyTransacPaymentModuleFrontController extends ModuleFrontController
{

	public $ssl = true;
	public $display_column_left = false;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();
		$cart = $this->context->cart;
		$customer = $this->context->customer;
		$user_address = new Address(intval($cart->id_address_invoice));
		$api_key = Configuration::get('EASYTRANSAC_API_KEY');
		$total = 100 * $cart->getOrderTotal(true, Cart::BOTH);
		$langcode = $this->context->language->iso_code == 'fr' ? 'FRE' : 'ENG';
		$this->module->loginit();
		EasyTransac\Core\Logger::getInstance()->write('Start Payment Page Request');
		EasyTransac\Core\Services::getInstance()->provideAPIKey($api_key);

		// SDK Payment Page
		$customer_ET = (new EasyTransac\Entities\Customer())
				->setEmail($customer->email)
				->setUid($user_address->id_customer)
				->setFirstname($customer->firstname)
				->setLastname($customer->lastname)
				->setAddress($user_address->address1 . ' - ' . $user_address->address2)
				->setZipCode($user_address->postcode)
				->setCity($user_address->city)
				->setBirthDate($customer->birthday == '0000-00-00' ? '' : $customer->birthday)
				->setNationality('')
				->setCallingCode('')
				->setPhone($user_address->phone);

		$transaction = (new EasyTransac\Entities\PaymentPageTransaction())
				->setAmount($total)
				->setCustomer($customer_ET)
				->setOrderId($this->context->cart->id)
				->setReturnUrl(Tools::getShopDomainSsl(true, true) . '/module/easytransac/validation')
				->setCancelUrl(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?controller=order&step=3')
				->setSecure(Configuration::get('EASYTRANSAC_3DSECURE') ? 'yes' : 'no')
				->setVersion($this->module->get_server_info_string())
				->setLanguage($langcode);

		// Cart description.
		if ($cart && ($products = $cart->getProducts()) && is_array($products))
		{
			$description = array();
			foreach ($products as $product)
			{
				if (isset($product['name']) && isset($product['cart_quantity']))
				{
					$description[] = $product['cart_quantity'] . 'x ' . $product['name'];
				}
			}
			if ($description)
			{
				$raw_text = implode(',', $description);
				$description_text = strlen($raw_text) > 255 ? substr($raw_text, 255) . '...' : $raw_text;
				$transaction->setDescription($description_text);
			}
		}

		$request = new EasyTransac\Requests\PaymentPage();

		/* @var  $response \EasyTransac\Entities\PaymentPageInfos */
		try
		{
			$response = $request->execute($transaction);
		}
		catch (Exception $exc)
		{
			EasyTransac\Core\Logger::getInstance()->write('Payment Exception: ' . $exc->getMessage());
		}


		if (!$response->isSuccess())
		{
			EasyTransac\Core\Logger::getInstance()->write('Payment Page Request error: ' . $response->getErrorCode() . ' - ' . $response->getErrorMessage());
		}

		// Store cart_id in session
		$this->context->cookie->cart_id = $this->context->cart->id;

		$this->context->cookie->order_total = $cart->getOrderTotal(true, Cart::BOTH);

		$this->context->smarty->assign(array(
			'nbProducts' => $cart->nbProducts(),
			'total' => $cart->getOrderTotal(true, Cart::BOTH),
			'payment_page_url' => $response->isSuccess() ? $response->getContent()->getPageUrl() : false,
		));

		$this->module->create_easytransac_order_state();

		$this->setTemplate('module:easytransac/views/templates/front/payment_execution.tpl');	
	}

}
