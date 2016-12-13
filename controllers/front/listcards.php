<?php

/**
 * EasyTransac listcards controller.
 */
class EasyTransacListcardsModuleFrontController extends ModuleFrontController
{

	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		if(!$this->context->customer->id) die;
		include_once(_PS_MODULE_DIR_ . 'easytransac/api.php');
		$easytransac_api	 = new EasyTransacApi();
		$output = array('status' => 0);
		$api_key = Configuration::get('EASYTRANSAC_API_KEY');
		$client_id = $this->context->customer->getClient_id();
		if (!empty($api_key) && !empty($client_id)) {
		  $data = array(
			"ClientId" => $client_id,
		  );
		  $easytransac_api = new EasytransacApi();
		  $response = $easytransac_api->setServiceListCards()->communicate($api_key, $data);

		  if (!empty($response['Result'])) {
			$buffer = array();
			foreach ($response['Result'] as $row)
			{
			  $buffer[] = array_intersect_key($row, array('Alias' => 1, 'CardNumber' => 1));
			}
			$output = array('status' => !empty($buffer), 'packet' => $buffer);
		  }
		}
		echo json_encode($output);
		
		die;
	}

}
