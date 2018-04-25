<?php
class PaytmValidationModuleFrontController extends ModuleFrontController
{
	public $warning = '';
	public $message = '';
	public function initContent()
	{
		parent::initContent();
	
		$this->context->smarty->assign(array(
			'warning' => $this->warning,
			'message' => $this->message
		));

		$this->setTemplate('module:paytm/views/templates/front/validation.tpl');
	}

	public function postProcess()
	{
		$merchant_id = Configuration::get('Paytm_MERCHANT_ID');
		$secret_key = Configuration::get('Paytm_MERCHANT_KEY');

		$paramList = $_POST;
		$order_id = $_POST['ORDERID'];
		$res_code = $_POST['RESPCODE'];
		$res_desc = $_POST['RESPMSG'];
		$checksum_recv = $_POST['CHECKSUMHASH'];
		$order_amount = $_POST['TXNAMOUNT'];
		
		$status_code = "";
		$bool = false;
		$bool = verifychecksum_e($paramList, $secret_key, $checksum_recv);

		$cart = $this->context->cart;
		$cart_id = $cart->id;

		$amount = $cart->getOrderTotal(true,Cart::BOTH);
		$responseMsg1 = $_POST['RESPMSG'];

		if ($bool == true) {
			// Create an array having all required parameters for status query.
			$requestParamList = array("MID" => $merchant_id , "ORDERID" => $_POST['ORDERID']);
			
			$StatusCheckSum = getChecksumFromArray($requestParamList,$secret_key);
				
			$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
		
			if ($res_code == "01") {
				$responseParamList = callNewAPI(Configuration::get('Paytm_TRANSACTION_STATUS_URL'), $requestParamList);
				if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$amount)
				{
					$status_code = "Ok";
					$message= $responseMsg1;
					$responseMsg= $responseMsg1;
					$status = Configuration::get('Paytm_ID_ORDER_SUCCESS');
				}
				else{
					$responseMsg = "It seems some issue in server to server communication. Kindly connect with administrator.";
					$message = 'Security Error !!';
					$status = Configuration::get('Paytm_ID_ORDER_FAILED');
					$status_code = "Failed";
				}					
			}
			 else if ($res_code == "141") {
				$responseMsg = "Transaction Cancelled. ";
				$message = $responseMsg1;
				$status = "6";
				$status_code = "Failed";
			} else  {
				$responseMsg = "Transaction Failed. ";
				$message = $responseMsg1;
				$status = Configuration::get('Paytm_ID_ORDER_FAILED');
				$status_code = "Failed";
			}			
			
		} else {
			$status_code = "Failed";
			$responseMsg = "Security Error ..!";
			$message = $responseMsg1;
			$status = Configuration::get('Paytm_ID_ORDER_FAILED');
		}

		$customer = new Customer($cart->id_customer);

		$this->module->validateOrder((int)$cart_id,  $status, (float)$amount, "Paytm", null, null, null, false, $customer->secure_key);

		if ($status == Configuration::get('Paytm_ID_ORDER_SUCCESS')) {
			Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
		} else {
			$this->message = $message;
			$this->warning= $responseMsg;
			$this->is_guest = $customer->is_guest;

			//Tools::redirect('index.php');
		}
	}
}
