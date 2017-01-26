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
		if (!$this->context->customer->id)
			die;
		$this->module->loginit();
		EasyTransac\Core\Services::getInstance()->provideAPIKey(Configuration::get('EASYTRANSAC_API_KEY'));
		$customer = (new EasyTransac\Entities\Customer())->setClientId($this->context->customer->getClient_id());
		$request = new EasyTransac\Requests\CreditCardsList();
		$response = $request->execute($customer);

		if ($response->isSuccess())
		{
			$buffer = array();
			foreach ($response->getContent()->getCreditCards() as $cc)
			{
				/* @var $cc EasyTransac\Entities\CreditCard */
				$buffer[] = array('Alias' => $cc->getAlias(), 'CardNumber' => $cc->getNumber());
			}
			$output = array('status' => !empty($buffer), 'packet' => $buffer);
			echo json_encode($output);
			die;
		}
		else
		{
			EasyTransac\Core\Logger::getInstance()->write('List Cards Error: ' . $response->getErrorCode() . ' - ' . $response->getErrorMessage());
		}
		echo json_encode(array('status' => 0));
	}

}
