<?php
require_once(dirname(__FILE__).'/../../lib/PaytmHelper.php');
require_once(dirname(__FILE__).'/../../lib/PaytmChecksum.php');

class PaytmResponseModuleFrontController extends ModuleFrontController {
	
	public function postProcess() {
        
		$merchant_id   = Configuration::get('Paytm_MERCHANT_ID');
		$secret_key    = Configuration::get('Paytm_MERCHANT_KEY');
		$paramList     = $_POST;
		$order_id      = $_POST['ORDERID'];
		$res_code      = $_POST['RESPCODE'];
		$res_desc      = $_POST['RESPMSG'];
		$checksum_recv = $_POST['CHECKSUMHASH'];
		$order_amount  = $_POST['TXNAMOUNT'];
        /* save paytm response in db */
			if(PaytmConstants::SAVE_PAYTM_RESPONSE && !empty($_POST['STATUS'])){
				$order_data_id = $this->saveTxnResponse($_POST, PaytmHelper::getOrderId($order_id));
				$update_response = $_POST;
			}
			/* save paytm response in db */
		$status_code = "";
		$bool = "FALSE";
		unset($_POST['CHECKSUMHASH']);
		$bool = PaytmChecksum::verifySignature($_POST, $secret_key, $checksum_recv);
		$cartID = $order_id;
		$extendstras = array();
		$extras['transaction_id'] = $_POST['TXNID'];
		$cart = new Cart(intval($cartID));
		$amount = $cart->getOrderTotal(true,Cart::BOTH);
		$responseMsg1 = $_POST['RESPMSG'];

		if($bool == "TRUE") {
			// Create an array having all required parameters for status query.
			$reqParams = array("MID" => $merchant_id , "ORDERID" => $_POST['ORDERID']);

			$StatusCheckSum = PaytmChecksum::generateSignature($reqParams, $secret_key);

			$reqParams['CHECKSUMHASH'] = $StatusCheckSum;
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
						$update_response['STATUS'] 	    = $resParams['STATUS'];
						$update_response['RESPCODE'] 	= $resParams['RESPCODE'];
						$update_response['RESPMSG'] 	= $resParams['RESPMSG'];

						$this->saveTxnResponse($update_response,PaytmHelper::getOrderId($resParams['ORDERID']), $order_data_id);
					}
					/* save paytm response in db */
					// if curl failed to fetch response
					if(!isset($resParams['STATUS'])){
						$responseMsg  = PaytmConstants::ERROR_SERVER_COMMUNICATION;
						$message      = 'Security Error !!';
						$status       = Configuration::get('Paytm_ID_ORDER_FAILED');
						$status_code  = "Failed";
	
					} else {
	
						if($resParams['STATUS'] == 'TXN_SUCCESS' 
							&& $resParams['TXNAMOUNT'] == $_POST['TXNAMOUNT']) {
							
								$status_code   = "Ok";
								$message       = $responseMsg1;
								$responseMsg   = $responseMsg1;
								$status        = Configuration::get('Paytm_ID_ORDER_SUCCESS');
						
						} elseif($resParams['STATUS'] == 'PENDING'){

							$status_code = "Pending";
							$responseMsg = PaytmConstants::TEXT_PENDING."<br/>".PaytmConstants::TEXT_REASON.$resParams['RESPMSG'];;
							$message     = "Pending";
							$status      = Configuration::get('Paytm_ID_ORDER_PENDING');
	
						}
						else {
							if($resParams['TXNAMOUNT'] != $_POST['TXNAMOUNT']) {
								
								$status_code    = "Failed";
								$responseMsg    = PaytmConstants::ERROR_AMOUNT_MISMATCH;
								$message        = 'Security Error !!';
								$status         = Configuration::get('Paytm_ID_ORDER_FAILED');
							//	Security Error. Amount Mismatched!
							} else if(isset($resParams['RESPMSG']) && !empty($resParams['RESPMSG'])){
								$status_code     = "Failed";
								$responseMsg     = PaytmConstants::TEXT_REASON.$resParams['RESPMSG'];
								$message         = $responseMsg1;
								$statuks         = Configuration::get('Paytm_ID_ORDER_FAILED');
								
							}
						}
					}
		        }
			    else {
					$status_code   = "Failed";
					$responseMsg   = PaytmConstants::TEXT_FAILURE;
					$message       = $responseMsg1;
					$status        = Configuration::get('Paytm_ID_ORDER_FAILED');
			   }
		    }
			else {
				$status_code = "Failed";
				$responseMsg = PaytmConstants::ERROR_INVALID_ORDER;
				$message     = $responseMsg1;
				$status      = Configuration::get('Paytm_ID_ORDER_FAILED');
			}
		} 
		else {
			$status_code    = "Failed";
			$message        = 'Security Error !!';
			$responseMsg    = PaytmConstants::ERROR_CHECKSUM_MISMATCH;
			$status         = Configuration::get('Paytm_ID_ORDER_FAILED');
			
		}


		$customer = new Customer($cart->id_customer);
		
		$history_message = $responseMsg.'. Paytm Payment ID: '.$_POST['TXNID'];

		$this->module->validateOrder(intval($cart->id), $status, $order_amount, $this->module->displayName, $history_message, $extras, '', false, $cart->secure_key);
		Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)$this->module->currentOrder);
		return;
	}

	private function saveTxnResponse($data  = array(),$order_id, $id = false){

		if(empty($data['STATUS']))return false;

		$status 			= (!empty($data['STATUS']) && $data['STATUS'] =='TXN_SUCCESS') ? 1 : 0;
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
