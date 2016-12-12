<?php

class EasyTransacValidationModuleFrontController extends ModuleFrontController
{
	
	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		include_once(_PS_MODULE_DIR_ . 'easytransac/api.php');

		$api	 = new EasyTransacApi();
		$api_key = Configuration::get('EASYTRANSAC_API_KEY');
		
		$debug = 0;
		$dump_path = __DIR__ . '/dump';
		
		if($debug)
		{
			$debug_string = 'Start Validation ' . date('c');
			file_put_contents($dump_path, "\n\n" . $debug_string, FILE_APPEND);
		}

		// Data received from EasyTransac
		$data = $_POST;

		// First minimal data validation
		$required_fields = array('RequestId', 'Tid', 'Uid', 'OrderId', 'Status', 'Signature');

		$https_enabled = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
		
		if($debug)
		{
			$debug_string = 'Validation order : ' . $this->context->cookie->order_id;
			file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
		}
		
		$cart = new Cart($this->context->cookie->cart_id);
		
		if(empty($cart->id))
		{
			Logger::AddLog('EasyTransac: Unknown Cart id');
			Tools::redirect('index.php?controller=order&step=1');
		}
                
		$existing_order_id = OrderCore::getOrderByCartId($this->context->cookie->cart_id);
		
		$existing_order = new Order($existing_order_id);
		
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
		
		// Pending status if HTTPS is not used.
		if (!$https_enabled)
		{
			// HTTP Only
			
			if($debug)
			{
				$debug_string = 'Validation HTTP';
				file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
			}

			// Redirect to validation page.
			if(empty($existing_order->id) || empty($existing_order->current_state))
			{
				Tools::redirect(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/spinner');
			}
			else
			{
				Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $this->context->cookie->cart_id . '&id_module=' . $this->module->id . '&id_order=' . $existing_order->id  . '&nohttps=1' . '&key=' . $this->context->customer->secure_key);
			}
			return;
		}
		
		if($debug)
		{
			$debug_string = 'Validation HTTPS';
			file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
		}

		// If HTTPS is used, POST data must be present.
		if (empty($data))
		{
			Logger::AddLog('EasyTransac: Invalid data received');
			Tools::redirect('index.php?controller=order&step=1');
			return;
		}

		// Check required fields.
		foreach ($required_fields as $field_key)
		{
			if (empty($data[$field_key]))
			{
				Logger::AddLog('EasyTransac: Invalid data received');
				Tools::redirect('index.php?controller=order&step=1');
				return;
			}
		}

		// Check the signature.
		$signature_is_valid = $api->easytransac__verify_signature($data, $api_key);

		if (!$signature_is_valid)
		{
			Logger::AddLog('EasyTransac: Signature is invalid');
			Tools::redirect('index.php?controller=order&step=1');
			return;
		}
                
                // Lock so that notification don't interfere
                if ($this->module->isLocked($data['OrderId']))
                {
                    if($debug)
                    {
                            $debug_string = 'Validation lock loop : ' . $data['OrderId'];
                            file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
                    }
                    // Wait loop (max 10s) to let the notification finish
                    $max = 10;
                    $i = 0;
                    while($i < $max)
                    {
                        sleep(1);
                        if (!$this->module->isLocked($data['OrderId']))
                                break;
                        $i++;
                    }
                }
                
                // Lock
                $this->module->lock($data['OrderId']);
                        
                if($debug)
                {
                        $debug_string = 'Validation locked : ' . $data['OrderId'];
                        file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
                }

                if($debug)
		{
			$debug_string = 'Validation for OrderId : ' . $data['OrderId'] . ', Status: ' . $data['Status'];
			file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
		}


		$payment_status = null;

		$payment_message = $data['Message'];

		if (!empty($data['Error']))
			$payment_message .= '.' . $data['Error'];

		if (!empty($data['AdditionalError']) && is_array($data['AdditionalError']))
			$payment_message .= '. ' . implode(' ', $data['AdditionalError']);

		// 2: payment accepted, 6: canceled, 7: refunded, 8: payment error, 12: remote payment accepted
		switch ($data['Status'])
		{
			case 'captured':
				$payment_status = 2;
				break;

			case 'pending':
				$payment_status = (int)Configuration::get('EASYTRANSAC_ID_ORDER_STATE');
				break;

			case 'refunded':
				$payment_status = 7;
				break;

			case 'failed':
			default :
				$payment_status = 8;
				break;
		}
		
		                
                // Check that paid amount matches cart total (v1.7)
                
                # String string format compare
                $paid_total = number_format($data['Amount'], 2, '.', '');
                $cart_total = number_format($cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
                $amount_match = $paid_total === $cart_total;
                
                if($debug)
                {
//                        $debug_string = print_r($data, true);
//                        file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                    
                        $debug_string = 'Validation Paid total: ' . $paid_total  . ' prestashop price: ' . $cart_total;
                        file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                }
                
                if( ! $amount_match && 2 == $payment_status)
                {
                    $payment_message = $this->module->l('Price paid on EasyTransac is not the same that on Prestashop - Transaction : ') . $data['Tid'];
                    $payment_status = 8;
                    
                    if($debug)
                    {
                            $debug_string = 'Validation Amount mismatch';
                            file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                    }
                }

		if(empty($existing_order->id))
		{
                        $total_paid_float = (float)$paid_total;
                        
			# Validate cart to order.
			$this->module->validateOrder($this->context->cart->id, (int)$payment_status, (float)$total_paid_float, $this->module->displayName, "", $mailVars = array(), null, false, $this->context->customer->secure_key);
                        if($debug)
                        {
                                $debug_string = 'Validation Order validated';
                                file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                        }
                        $order_id = $this->module->currentOrder;
		}
                else
                {
                    $order_id = $existing_order->id;
                }
                
		if(((int)$existing_order->current_state != 2 || (int)$payment_status == 7) && (int)$existing_order->current_state != (int)$payment_status )
		{
			// Updating the order's state only if current state is not captured
                        // or if target state is refunded
			
			// Updating the order's state
			$existing_order->setCurrentState($payment_status);
                        
                        // Add message
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
			if($debug)
			{
				$debug_string = 'Validation change state to: ' . $payment_status;
				file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
			}
		}
		elseif(empty($existing_order->id) && empty($existing_order->current_state))
		{
                        $this->module->unlock($data['OrderId']);
                        
			# Currently processed by notification.
			Tools::redirect(Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'module/easytransac/spinner');
			return;
		}
		# else : order exists and no state change
		
                if($debug)
                {
                        $debug_string = 'Validation End of Script';
                        file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                }

                $this->module->unlock($data['OrderId']);
		Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $this->context->cookie->cart_id . '&id_module=' . $this->module->id . '&id_order=' . $order_id.  '&key=' . $this->context->customer->secure_key);
	}

}
