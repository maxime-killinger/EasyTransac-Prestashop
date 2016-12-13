<?php

/**
 * EasyTransac notification controller.
 */
class EasyTransacNotificationModuleFrontController extends ModuleFrontController
{

	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
//		$c = get
//		echo $c->getClient_id();
//		$c->id = $this->context->customer->id;
//		$this->context->customer->setClient_id(666);
//		echo $this->context->customer->getClient_id();
//		echo get_class($this->context->customer);
//		$found = $c->getByClientId(666);
//		var_dump($found);
//		die('hhh');
		include_once(_PS_MODULE_DIR_ . 'easytransac/api.php');

		$debug = 1;
		$dump_path = __DIR__ . '/dump';
		
		if($debug)
		{
			$debug_string = 'Start Notification ' . date('c');
			file_put_contents($dump_path, "\n\n" .$debug_string, FILE_APPEND);
			file_put_contents($dump_path, "\n\n" .print_r($_POST, true), FILE_APPEND);
		}

		$api	 = new EasyTransacApi();
		$api_key = Configuration::get('EASYTRANSAC_API_KEY');
		$data	 = $_POST;

		// First minimal data validation
		$required_fields = array('RequestId', 'Tid', 'Uid', 'OrderId', 'Status', 'Signature');

		if (empty($data))
		{
			die('No data');
		}

		foreach ($required_fields as $field_key)
		{
			if (empty($data[$field_key]))
			{
				die('Error required');
			}
		}


		$signature_is_valid = $api->easytransac__verify_signature($data, $api_key);

		if (!$signature_is_valid)
		{
			die('Error sig');
		}
                
                // Lock so that validation don't interfere
                if ($this->module->isLocked($data['OrderId']))
                {
                    if($debug)
                    {
                            $debug_string = 'Notification lock loop : ' . $data['OrderId'];
                            file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
                    }
                    
                    // Wait loop (max 10s) to let the validation finish
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
                        $debug_string = 'Notification locked : ' . $data['OrderId'];
                        file_put_contents($dump_path, "\n" . $debug_string, FILE_APPEND);
                }
                
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
		
		$cart = new Cart($data['OrderId']);
		
		if(empty($cart->id))
		{
			Logger::AddLog('EasyTransac: Unknown Cart id');
                        $this->module->unlock($data['OrderId']);
			die;
		}
		$customer = new Customer($cart->id_customer);
		
		$existing_order_id = OrderCore::getOrderByCartId($data['OrderId']);
		$existing_order = new Order($existing_order_id);

		if($debug)
		{
			$debug_string = 'Notification customer : ' . $existing_order->id_customer;
			file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
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
				$customer->setClient_id($data['Client']['Id']);
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
                
                if($debug)
		{
			$debug_string = 'Notification for OrderId : ' . $data['OrderId'] . ', Status: ' . $data['Status'] . ', Prestashop Status: ' . $payment_status;
			file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
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
                    
                        $debug_string = 'Notification Paid total: ' . $paid_total  . ' prestashop price: ' . $cart_total;
                        file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                }
                
                // Useful if amount doesn't match and it's an update
                $original_new_state = $payment_status;
                        
                if( ! $amount_match && 2 == $payment_status)
                {
                    $payment_message = $this->module->l('Price paid on EasyTransac is not the same that on Prestashop - Transaction : ') . $data['Tid'];
                    $payment_status = 8;
                    
                    if($debug)
                    {
                            $debug_string = 'Notification Amount mismatch';
                            file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                    }
                }
                

		if(empty($existing_order->id) || empty($existing_order->current_state))
		{
                        $total_paid_float = (float)$paid_total;
                        
                        if($debug)
                        {
                                $debug_string = 'Notification Total paid float: ' . $total_paid_float;
                                file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                        }
                        
			$this->module->validateOrder($cart->id, $payment_status, $total_paid_float, $this->module->displayName, $payment_message, $mailVars = array(), null, false, $customer->secure_key);
                        $this->module->unlock($data['OrderId']);
                        if($debug)
                        {
                                $debug_string = 'Notification Order validated';
                                file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                        }
                        die('Presta-v'.$this->module->version.'-OK');
		}

		if(((int)$existing_order->current_state != 2 || (int)$payment_status == 7) && (int)$existing_order->current_state != (int)$original_new_state )
		{
			// Updating the order's state only if current state is not captured
                        // or if target state is refunded
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

			if($debug)
			{
				$debug_string = 'Notification : order state changed to : ' . $payment_status;
				file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
			}
		}
		elseif($debug)
		{
			$debug_string = 'Notification : invalid target state or same state as: ' . $payment_status;
			file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
		}

               	if($debug)
                {
                        $debug_string = 'Notification End of Script';
                        file_put_contents($dump_path, "\n" .$debug_string, FILE_APPEND);
                }
                $this->module->unlock($data['OrderId']);
		die('Presta-v'.$this->module->version.'-OK');
	}

}
