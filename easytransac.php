<?php

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

/*
 * EasyTransac's official payment method Prestashop module.
 */

if (!defined('_PS_VERSION_'))
	exit;

class EasyTransac extends PaymentModule
{

	public function __construct()
	{
		$this->name = 'easytransac';
		$this->tab = 'payments_gateways';
		$this->version = '2.1';
		$this->author = 'EasyTransac';
		$this->is_eu_compatible = 1;
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('EasyTransac');
		$this->description = $this->l('Website payment service');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

		if (!Configuration::get('EASYTRANSAC_API_KEY'))
			$this->warning = $this->l('No API key provided');
	}
	
	public function loginit()
	{
		EasyTransac\Core\Logger::getInstance()->setActive(Configuration::get('EASYTRANSAC_DEBUG'));
		EasyTransac\Core\Logger::getInstance()->setFilePath(_PS_ROOT_DIR_ . '/modules/easytransac/logs/');
	}

	/**
	 * Module install.
	 * @return boolean
	 */
	public function install()
	{
		if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn'))
			return false;
		include_once(_PS_MODULE_DIR_ . $this->name . '/easytransac_install.php');
		$easytransac_install = new EasyTransacInstall();
		$easytransac_install->updateConfiguration();
		$easytransac_install->createTables();
		$this->create_easytransac_order_state();
		return true;
	}

	/**
	 * Module uninstall.
	 * @return boolean
	 */
	public function uninstall()
	{
		if (!parent::uninstall())
			return false;
		include_once(_PS_MODULE_DIR_ . $this->name . '/easytransac_install.php');
		$easytransac_install = new EasyTransacInstall();
		$easytransac_install->deleteConfiguration();
		return true;
	}

	/**
	 * Settings page.
	 */
	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('submit' . $this->name))
		{
			$api_key = strval(Tools::getValue('EASYTRANSAC_API_KEY'));
			if (!empty($api_key))
			{
				Configuration::updateValue('EASYTRANSAC_API_KEY', $api_key);
			}

			$enable_3dsecure = strval(Tools::getValue('EASYTRANSAC_3DSECURE'));
			if (empty($enable_3dsecure))
			{
				Configuration::updateValue('EASYTRANSAC_3DSECURE', 0);
			}
			else
			{
				Configuration::updateValue('EASYTRANSAC_3DSECURE', 1);
			}
			
			$enable_debug = strval(Tools::getValue('EASYTRANSAC_DEBUG'));
			if (empty($enable_debug))
			{
				Configuration::updateValue('EASYTRANSAC_DEBUG', 0);
				$this->loginit();
				EasyTransac\Core\Logger::getInstance()->delete();
			}
			else
			{
				Configuration::updateValue('EASYTRANSAC_DEBUG', 1);
			}
			$output .= $this->displayConfirmation($this->l('Settings updated'));
		}

		return $output . $this->displayForm();
	}

	/**
	 * Settings form.
	 */
	public function displayForm()
	{
		// Get default language
		$default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
		$requirements_message = '';

		// Requirements.
		$openssl_version_supported = OPENSSL_VERSION_NUMBER >= 0x10001000;
		$curl_activated = function_exists('curl_version');

		if ($openssl_version_supported)
		{
			$requirements_message = '<div class="alert-success" style="padding:5px;">' . $this->l('[OK] OpenSSL version') . ' "' . OPENSSL_VERSION_TEXT . '" >= 1.0.1' . '</div>';
		}
		else
		{
			$requirements_message = '<div class="alert-danger" style="padding:5px;">' . $this->l('[ERROR] OpenSSL version not supported') . ' "' . OPENSSL_VERSION_TEXT . '" < 1.0.1</div>';
		}

		if ($curl_activated)
		{
			$requirements_message .= '<div class="alert-success" style="padding:5px;">' . $this->l('[OK] cURL is installed') . '</div>';
		}
		else
		{
			$requirements_message .= '<div class="alert-danger" style="padding:5px;">' . $this->l('[ERROR] PHP cURL extension missing') . '</div>';
		}

		// Init Fields form array
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Settings'),
			),
			'input' => array(
				array(
					'type' => 'radio',
					'label' => $this->l('3DSecure transactions only'),
					'desc' => $this->l('3DSecure is a secure payment protocol. Its aim is to reduce fraud for merchants and secure customer payments. The customer will be redirected to his bank\'s site that will ask for additional information.'),
					'name' => 'EASYTRANSAC_3DSECURE',
					'size' => 20,
					'is_bool' => true,
					'values' => array(// $values contains the data itself.
						array(
							'id' => 'active_on', // The content of the 'id' attribute of the <input> tag, and of the 'for' attribute for the <label> tag.
							'value' => 1, // The content of the 'value' attribute of the <input> tag.   
							'label' => $this->l('Enabled')	  // The <label> for this radio button.
						),
						array(
							'id' => 'active_off',
							'value' => 0,
							'label' => $this->l('Disabled')
						)
					),
				),
				array(
					'type' => 'radio',
					'label' => 'Debug Mode',
					'name' => 'EASYTRANSAC_DEBUG',
					'size' => 20,
					'is_bool' => true,
					'values' => array(// $values contains the data itself.
						array(
							'id' => 'active2_on', // The content of the 'id' attribute of the <input> tag, and of the 'for' attribute for the <label> tag.
							'value' => 1, // The content of the 'value' attribute of the <input> tag.   
							'label' => $this->l('Enabled')   // The <label> for this radio button.
						),
						array(
							'id' => 'active2_off',
							'value' => 0,
							'label' => $this->l('Disabled')
						)
					),
				),
				array(
					'type' => 'free',
					'label' => $this->l('Configuration'),
					'desc' => $this->l('Create an application configuration and copy paste your API key in the next input.'),
					'name' => 'EASYTRANSAC_HELP',
					'size' => 20,
				),
				array(
					'type' => 'free',
					'label' => $this->l('Requirements'),
					'desc' => $requirements_message,
					'name' => 'EASYTRANSAC_REQUIREMENTS_HELP',
					'size' => 20,
				),
				array(
					'type' => 'text',
					'label' => $this->l('EasyTransac Api Key'),
					'name' => 'EASYTRANSAC_API_KEY',
					'size' => 20,
					'required' => true
				),
				array(
					'type' => 'free',
					'label' => $this->l('Notification URL'),
					'desc' => $this->l('Notification URL to copy paste in your EasyTransac appplication settings'),
					'name' => 'EASYTRANSAC_NOTIFICATION_URL',
					'size' => 20,
				),
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);

		$helper = new HelperForm();

		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true; // false -> remove toolbar
		$helper->toolbar_scroll = true;   // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit' . $this->name;
		$helper->toolbar_btn = array(
			'save' =>
			array(
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
				'&token=' . Tools::getAdminTokenLite('AdminModules'),
			),
			'back' => array(
				'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);

		// Load current value
		$helper->fields_value['EASYTRANSAC_DEBUG'] = Configuration::get('EASYTRANSAC_DEBUG');
		$helper->fields_value['EASYTRANSAC_API_KEY'] = Configuration::get('EASYTRANSAC_API_KEY');
		$helper->fields_value['EASYTRANSAC_3DSECURE'] = Configuration::get('EASYTRANSAC_3DSECURE');
		$helper->fields_value['EASYTRANSAC_NOTIFICATION_URL'] = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/notification';
		$helper->fields_value['EASYTRANSAC_HELP'] = $this->l('Visit') . ' <a target="_blank" href="https://www.easytransac.com">www.easytransac.com</a> ' . $this->l('in order to create an account and configure your application.');

		return $helper->generateForm($fields_form);
	}

	/**
	 * Payment method choice.
	 */
	public function hookPayment($params)
	{
		if (!$this->active)
			return;

		return $this->fetchTemplate('checkout_payment.tpl');
	}

	/**
	 * Helper function to fetch a template.
	 */
	public function fetchTemplate($name)
	{
		if (version_compare(_PS_VERSION_, '1.4', '<'))
			$this->context->smarty->currentTemplate = $name;
		elseif (version_compare(_PS_VERSION_, '1.5', '<'))
		{
			$views = 'views/templates/';
			if (@filemtime(dirname(__FILE__) . '/' . $name))
				return $this->display(__FILE__, $name);
			elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'hook/' . $name))
				return $this->display(__FILE__, $views . 'hook/' . $name);
			elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'front/' . $name))
				return $this->display(__FILE__, $views . 'front/' . $name);
			elseif (@filemtime(dirname(__FILE__) . '/' . $views . 'admin/' . $name))
				return $this->display(__FILE__, $views . 'admin/' . $name);
		}
		return $this->display(__FILE__, $name);
	}

	/**
	 * Return from EasyTransac and validation.
	 */
	public function hookPaymentReturn()
	{
		if (!$this->active)
			return null;

		$this->create_easytransac_order_state();
		$et_pending = (int) Configuration::get('EASYTRANSAC_ID_ORDER_STATE');

		$existing_order = !empty($_GET['id_order']) ? new Order($_GET['id_order']) : null;

		if (empty($existing_order->id) || empty($existing_order->current_state) || $this->isLocked($existing_order->id))
			$existing_order->current_state = $et_pending;

		// 2: payment accepted, 6: canceled, 7: refunded, 8: payment error, 12: remote payment accepted
		$this->context->smarty->assign(array(
			'isPending' => (int) $existing_order->current_state === $et_pending,
			'isCanceled' => (int) $existing_order->current_state === 6 || (int) $existing_order->current_state === 8,
			'isAccepted' => (int) $existing_order->current_state === 2,
		));

		return $this->fetchTemplate('confirmation.tpl');
	}

	/**
	 * Make an order out of a cart.
	 */
	public function validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown', $message = null, $transaction = array(), $currency_special = null, $dont_touch_amount = false, $secure_key = false, Shop $shop = null)
	{
		if ($this->active)
		{
			if (version_compare(_PS_VERSION_, '1.5', '<'))
				parent::validateOrder((int) $id_cart, (int) $id_order_state, (float) $amount_paid, $payment_method, $message, $transaction, $currency_special, $dont_touch_amount, $secure_key);
			else
				parent::validateOrder((int) $id_cart, (int) $id_order_state, (float) $amount_paid, $payment_method, $message, $transaction, $currency_special, $dont_touch_amount, $secure_key, $shop);
		}
	}

	/**
	 * Locks an order.
	 * @param in $id
	 */
	public function lock($id)
	{
		$path = __DIR__ . '/EasyTransac-Processing-' . $id;

		if (is_writable(__DIR__))
			touch($path);
	}

	/**
	 * Check if an order is locked.
	 * @param int $id
	 * @return bool
	 */
	public function isLocked($id)
	{
		$path = __DIR__ . '/EasyTransac-Processing-' . $id;
		return file_exists($path);
	}

	/**
	 * Unlocks an order.
	 * @param int $id
	 */
	public function unlock($id)
	{
		$path = __DIR__ . '/EasyTransac-Processing-' . $id;
		if (file_exists($path) && is_writable(__DIR__))
			unlink($path);
	}

	/**
	 * Plugin version info.
	 * @return string
	 */
	public function get_server_info_string()
	{
		$curl_info_string = function_exists('curl_version') ? 'enabled' : 'not found';
		$openssl_info_string = OPENSSL_VERSION_NUMBER >= 0x10001000 ? 'TLSv1.2' : 'OpenSSL version deprecated';
		$https_info_string = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'S' : '';
		return sprintf('Prestashop %s [cURL %s, OpenSSL %s, HTTP%s]', $this->version, $curl_info_string, $openssl_info_string, $https_info_string);
	}

	/**
	 * Creates EasyTransac order state if not already registered.
	 * Prupose : plugin update from older versions.
	 */
	public function create_easytransac_order_state()
	{
		if (!(Configuration::get('EASYTRANSAC_ID_ORDER_STATE') > 0))
		{
			// for sites upgrading from older version
			$OrderState = new OrderState();
			$OrderState->id = 'EASYTRANSAC_STATE_ID';
			$OrderState->name = array_fill(0, 10, "EasyTransac payment pending");
			$OrderState->send_email = 0;
			$OrderState->invoice = 0;
			$OrderState->color = "#ff9900";
			$OrderState->unremovable = false;
			$OrderState->logable = 0;
			$OrderState->add();
			Configuration::updateValue('EASYTRANSAC_ID_ORDER_STATE', $OrderState->id);
		}
	}

}
