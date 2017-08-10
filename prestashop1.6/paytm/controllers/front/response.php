<?php
require_once(dirname(__FILE__).'/../../lib/encdec_paytm.php');
class PaytmResponseModuleFrontController extends ModuleFrontController {
	public function postProcess() {
		
		$order_id      = $_POST['ORDERID'];
		$res_code      = $_POST['RESPCODE'];
		$res_desc      = $_POST['RESPMSG'];
		$checksum_recv = $_POST['CHECKSUMHASH'];
		$paramList     = $_POST;
		$secret_key	   = Configuration::get('PayTM_SECRET_KEY');
		$merchant_id	   = Configuration::get('PayTM_MERCHANT_ID');
		$order_amount  = $_POST['TXNAMOUNT'];
				
		$bool = "FALSE";
		$bool = verifychecksum_e($paramList, $secret_key, $checksum_recv);
		$cartID = $order_id;
		$extras = array();
		$extras['transaction_id'] = $_POST['TXNID'];
		$cart = new Cart(intval($cartID));
		$amount = $cart->getOrderTotal(true,Cart::BOTH);
		$responseMsg = $_POST['RESPMSG'];
		$mode = Configuration::get('PayTM_MODE');
		if ($bool == "TRUE") {
			// Create an array having all required parameters for status query.
			$requestParamList = array("MID" => $merchant_id , "ORDERID" => $_POST['ORDERID']);
			
			$StatusCheckSum = getChecksumFromArray($requestParamList,$secret_key);
					
			$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
			
			// Call the PG's getTxnStatus() function for verifying the transaction status.
			
			if($mode=="TEST")
			{
				$check_status_url = 'https://pguat.paytm.com/oltp/HANDLER_INTERNAL/getTxnStatus';
			}
			else
			{
				$check_status_url = 'https://secure.paytm.in/oltp/HANDLER_INTERNAL/getTxnStatus';
			}						
				if ($res_code == "01") {
					$responseParamList = callNewAPI($check_status_url, $requestParamList);
					if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$amount)
					{
						$status_code = "Ok";
						$message= "Transaction Successful";
						$status = Configuration::get('Paytm_ID_ORDER_SUCCESS');
					}
					else{
						$responseMsg = "It seems some issue in server to server communication. Kindly connect with administrator.";
						$message = "Transaction Failed";
						$status = Configuration::get('Paytm_ID_ORDER_FAILED');
					}					
				}
				 else if ($res_code == "141") {
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
		$history_message = $responseMsg.'. Paytm Payment ID: '.$_POST['TXNID'];		
		
		$obj = new Paytm();
		
		$obj->validateOrder(intval($cart->id), $status, $order_amount, $obj->displayName, $history_message, $extras, '', false, $cart->secure_key);					
		$this->context->smarty->assign(array(
			'status' => $status_code,
			'responseMsg' => $message,
			'this_path' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));
		$cart_qties == 0;
		$cart->delete();
		$this->setTemplate('payment_response.tpl');
}
}
