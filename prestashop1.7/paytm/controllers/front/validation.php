<?php
require_once(dirname(__FILE__).'/../../lib/PaytmHelper.php');
require_once(dirname(__FILE__).'/../../lib/PaytmChecksum.php');

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
		$cart    = $this->context->cart;
		$cart_id = $cart->id;

		if ($cart->id == null || $cart->id_customer == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
			Tools::redirect('index.php?controller=order&step=1');
		}
		$merchant_id     = Configuration::get('Paytm_MERCHANT_ID');
		$secret_key      = Configuration::get('Paytm_MERCHANT_KEY');

		$paramList       = $_POST;
		$order_id        = $_POST['ORDERID'];
		$res_code        = $_POST['RESPCODE'];
		$res_desc        = $_POST['RESPMSG'];
		$checksum_recv   = $_POST['CHECKSUMHASH'];
		$order_amount    = $_POST['TXNAMOUNT'];

		$extra_params = array(); // extra order params to attach with order
		if(isset($_POST['TXNID'])) {
			$extra_params['transaction_id'] = $_POST['TXNID'];
		}
		
			/* save paytm response in db */
			if(PaytmConstants::SAVE_PAYTM_RESPONSE && !empty($_POST['STATUS'])){
				$order_data_id   = $this->saveTxnResponse($_POST, PaytmHelper::getOrderId($order_id));
				$update_response = $_POST;
			}
			/* save paytm response in db */
		$status_code  = "";
		$bool         = false;

		unset($_POST['CHECKSUMHASH']);
		$bool         = PaytmChecksum::verifySignature($_POST, $secret_key, $checksum_recv);

		$cart         = $this->context->cart;
		$cart_id      = $cart->id;
		$amount       = $cart->getOrderTotal(true,Cart::BOTH);
		$responseMsg1 = $_POST['RESPMSG'];
		if ($bool == true) {
			// Create an array having all required parameters for status query.
			$reqParams                 = array("MID" => $merchant_id , "ORDERID" => $order_id);	
			$reqParams['CHECKSUMHASH'] = PaytmChecksum::generateSignature($reqParams,$secret_key);
				
			if($order_id) {

				if(isset($_POST['STATUS']) && ($_POST['STATUS'] == "TXN_SUCCESS" || $_POST['STATUS'] == "PENDING" )) {

				/* number of retries untill cURL gets success */
				$retry = 1;
				do{
					$resParams = PaytmHelper::executecUrl(PaytmHelper::getTransactionStatusURL(Configuration::get('Paytm_ENVIRONMENT')), $reqParams);
					$retry++;
				} while(!$resParams['STATUS'] && $retry < PaytmConstants::MAX_RETRY_COUNT);
					/* save paytm response in db */
					if(PaytmConstants::SAVE_PAYTM_RESPONSE && !empty($resParams['STATUS'])){
						$update_response['STATUS']  	= $resParams['STATUS'];
						$update_response['RESPCODE'] 	= $resParams['RESPCODE'];
						$update_response['RESPMSG'] 	= $resParams['RESPMSG'];

						$this->saveTxnResponse($update_response,PaytmHelper::getOrderId($resParams['ORDERID']), $order_data_id);
					}
					/* save paytm response in db */
			    //retry 3 times for transaction status if we dont get reponse	
				// if curl failed to fetch response
				if(!isset($resParams['STATUS'])){
					$responseMsg  = PaytmConstants::ERROR_SERVER_COMMUNICATION;
					$message      = 'Security Error !!';
					$status       = Configuration::get('Paytm_ID_ORDER_FAILED');
					$status_code  = "Failed";

				} else {
					if($resParams['STATUS'] == 'TXN_SUCCESS' 
						&& $resParams['TXNAMOUNT'] == $_POST['TXNAMOUNT']) {
						
							$status_code = "Ok";
							$message     = $responseMsg1;
							$responseMsg = $responseMsg1;
							$status      = Configuration::get('Paytm_ID_ORDER_SUCCESS');
					
					} elseif($resParams['STATUS'] == 'PENDING'){

						$status_code = "Pending";
						$responseMsg = PaytmConstants::TEXT_PENDING."<br/>".PaytmConstants::TEXT_REASON.$resParams['RESPMSG'];;
						$message     = "Pending";
						$status      = Configuration::get('Paytm_ID_ORDER_PENDING');

					}
					else {
						if($resParams['TXNAMOUNT'] != $_POST['TXNAMOUNT']) {
							
							$status_code = "Failed";
							$responseMsg = PaytmConstants::ERROR_AMOUNT_MISMATCH;
							$message     = "Security Error !!";
							$status      = Configuration::get('Paytm_ID_ORDER_FAILED');
						//	Security Error. Amount Mismatched!
						} else if(isset($resParams['RESPMSG']) && !empty($resParams['RESPMSG'])){
							$status_code = "Failed";
							$responseMsg = PaytmConstants::TEXT_REASON.$resParams['RESPMSG'];
							$message     = $responseMsg1;
							$status      = Configuration::get('Paytm_ID_ORDER_FAILED');
							
						    }    
						
					    }
				}
			}
			else {
			$status_code  = "Failed";
			$responseMsg  = PaytmConstants::TEXT_FAILURE;
			$message      = $responseMsg1;
			$status       = Configuration::get('Paytm_ID_ORDER_FAILED');
			}
		}
		else {
			$status_code  = "Failed";
			$responseMsg  = PaytmConstants::ERROR_INVALID_ORDER;
			$message      = $responseMsg1;
			$status       = Configuration::get('Paytm_ID_ORDER_FAILED');
		}
        //new block ends for retry 3 times for transaction status if we dont get reponse
		} else {
			$status_code = "Failed";
			$message     = "Security Error !!";
			$responseMsg = PaytmConstants::ERROR_CHECKSUM_MISMATCH;
			$status      = Configuration::get('Paytm_ID_ORDER_FAILED');
			
		}
		$customer        = new Customer($cart->id_customer);
		$this->module->validateOrder((int)$cart_id,  $status, (float)$amount, "Paytm", null, $extra_params, null, false, $customer->secure_key);

		if ($status == Configuration::get('Paytm_ID_ORDER_SUCCESS')) {
			Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
		} else {
			$this->message  = $message;
			$this->warning  = $responseMsg;
			$this->is_guest = $customer->is_guest;
		}
	}

	private function saveTxnResponse($data  = array(),$order_id, $id = false){
		
		if(empty($data['STATUS']))return false;
		 
		$status 		    = (!empty($data['STATUS']) && $data['STATUS'] =='TXN_SUCCESS') ? 1 : 0;
		$paytm_order_id 	= (!empty($data['ORDERID'])? $data['ORDERID']:'');
		$transaction_id 	= (!empty($data['TXNID'])? $data['TXNID']:'');
		
		if($id !== false){
		$sql =  "UPDATE " . _DB_PREFIX_ . "paytm_order_data SET order_id = '" . $order_id . "', paytm_order_id = '" . $paytm_order_id . "', transaction_id = '" . $transaction_id . "', status = '" . (int)$status . "', paytm_response = '" . json_encode($data) . "', date_modified = NOW() WHERE id = '" . (int)$id . "'";
			 Db::getInstance()->execute($sql);
			return $id;
		}else{
			 $sql =  "INSERT INTO " . _DB_PREFIX_ . "paytm_order_data SET order_id = '" . $order_id . "', paytm_order_id = '" . $paytm_order_id . "', transaction_id = '" . $transaction_id . "', status = '" . (int)$status . "', paytm_response = '" . json_encode($data) . "', date_added = NOW(), date_modified = NOW()";
			return Db::getInstance()->execute($sql);
		}	
	}
}
