<?php
require_once(dirname(__FILE__).'/../../lib/encdec_paytm.php');
class PaytmResponseModuleFrontController extends ModuleFrontController {
	public function postProcess() {

		/* make log for all payment response */
		if(Configuration::get('Paytm_ENABLE_LOG')){
			$log_entry = "Reponse Type: Process Transaction (DEFAULT)". PHP_EOL;
			$log_entry .= "Reponse Params: " . print_r($_POST, true) .PHP_EOL.PHP_EOL;
			Paytm::addLog($log_entry, __FILE__, __LINE__);
		}
		/* make log for all payment response */

		$merchant_id = Configuration::get('Paytm_MERCHANT_ID');
		$secret_key = Configuration::get('Paytm_MERCHANT_KEY');
		
		$paramList = $_POST;
		$order_id = $_POST['ORDERID'];
		$res_code = $_POST['RESPCODE'];
		$res_desc = $_POST['RESPMSG'];
		$checksum_recv = $_POST['CHECKSUMHASH'];
		$order_amount = $_POST['TXNAMOUNT'];

		$status_code = "";
		$bool = "FALSE";
		$bool = verifychecksum_e($paramList, $secret_key, $checksum_recv);
		$cartID = $order_id;
		
		// $cartID = "7";  // just for testing.

		$extendstras = array();
		$extras['transaction_id'] = $_POST['TXNID'];
		$cart = new Cart(intval($cartID));
		$amount = $cart->getOrderTotal(true,Cart::BOTH);
		$responseMsg = $_POST['RESPMSG'];

		if ($bool == "TRUE") {
			// Create an array having all required parameters for status query.
			$requestParamList = array("MID" => $merchant_id , "ORDERID" => $_POST['ORDERID']);

			$StatusCheckSum = getChecksumFromArray($requestParamList, $secret_key);

			$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;

			if ($res_code == "01") {

				/* make log for all transaction status request */
				if(Configuration::get('Paytm_ENABLE_LOG')){
					$log_entry = "Request Type: Get Transaction Status". PHP_EOL;
					$log_entry .= "Request URL: ". Configuration::get('Paytm_TRANSACTION_STATUS_URL') .PHP_EOL;
					$log_entry .= "Request Params: " . print_r($_POST, true) .PHP_EOL.PHP_EOL;
					Paytm::addLog($log_entry, __FILE__, __LINE__);
				}
				/* make log for all transaction status request */

				$responseParamList = callNewAPI(Configuration::get('Paytm_TRANSACTION_STATUS_URL'), $requestParamList);

				/* make log for all transaction status reponse */
				if(Configuration::get('Paytm_ENABLE_LOG')){
					$log_entry = "Response Type: Get Transaction Status". PHP_EOL;
					$log_entry .= "Response Params: " . print_r($_POST, true) .PHP_EOL.PHP_EOL;
					Paytm::addLog($log_entry, __FILE__, __LINE__);
				}
				/* make log for all transaction status reponse */


				if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$amount) {
					$status_code = "ok";
					$message= "Transaction Successful";
					$status = Configuration::get('Paytm_ID_ORDER_SUCCESS');
				} else{
					$responseMsg = "It seems some issue in server to server communication. Kindly connect with administrator.";
					$message = "Transaction Failed";
					$status = Configuration::get('Paytm_ID_ORDER_FAILED');
				}
			} else if ($res_code == "141") {
				$responseMsg = "Transaction Cancelled. ";
				$message = "Transaction Cancelled";
				$status = "6";
			} else  {
				$responseMsg = "Transaction Failed. ";
				$message = "Transaction Failed";
				$status = Configuration::get('Paytm_ID_ORDER_FAILED');
			}

		} else {
			$status_code = "Failed";
			$responseMsg = "Security Error ..!";
			$status = Configuration::get('Paytm_ID_ORDER_FAILED');
		}


		$customer = new Customer($cart->id_customer);
		
		$history_message = $responseMsg.'. Paytm Payment ID: '.$_POST['TXNID'];

		$this->module->validateOrder(intval($cart->id), $status, $order_amount, $this->module->displayName, $history_message, $extras, '', false, $cart->secure_key);
		Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)$this->module->currentOrder);
		return;
	}
}	
