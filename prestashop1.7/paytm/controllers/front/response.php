<?php
//require_once(dirname(__FILE__).'/../../lib/Rc43.php');
require_once(dirname(__FILE__).'/../../lib/encdec_paytm.php');
class PaytmResponseModuleFrontController extends ModuleFrontController {
	public function postProcess() {
		$order_id      = $_POST['ORDERID'];
		$res_code      = $_POST['RESPCODE'];
		$res_desc      = $_POST['RESPMSG'];
		$checksum_recv = $_POST['CHECKSUMHASH'];
		$paramList     = $_POST;
		
		//var_dump($paramList);
		$secret_key	   = Configuration::get('PayTM_SECRET_KEY');
		$merchant_id	   = Configuration::get('PayTM_MERCHANT_ID');
		$order_amount  = $_POST['TXNAMOUNT'];
				
		$bool = "FALSE";
		//echo "<pre>"; print_r($paramList); echo 11;
		$bool = verifychecksum_e($paramList, $secret_key, $checksum_recv);
		
		/*if(isset($DR)){
			$DR = preg_replace("/\s/","+",$DR);
			$rc4 = new Crypt_RC4($secret_key);
			$QueryString = base64_decode($DR);
			$rc4->decrypt($QueryString);
			$QueryString = explode('&',$QueryString);
			$response = array();
			foreach($QueryString as $param){
				$param = explode('=',$param);
				$response[$param[0]] = urldecode($param[1]);
				array(8) { ["RESPCODE"]=> string(3) "141" ["RESPMSG"]=> string(26) "Cancel Request by Customer" ["STATUS"]=> string(11) "TXN_FAILURE" ["MID"]=> string(20) "pebble49164290093828" ["TXNAMOUNT"]=> string(3) "199" ["ORDERID"]=> string(4) "1105" ["TXNID"]=> string(4) "9051" ["CHECKSUMHASH"]=> string(108) "8JTqSis+Uqe2iVMo/vWLgjFQkay2pZQkoN/uUVaBbkZrwkYEZMXIKfKy9NfYd2Fk9JaHiemzwNVpfRJrqiWzyeDWxZSJBhCi5NBEaTdbcZA=" } 
			}
		}*/		
			
		$cartID = $order_id;
		$extras = array();
		$extras['transaction_id'] = $_POST['TXNID'];
		//$cart = new Cart(intval($cartID));
		$cart = $this->context->cart;
		//echo "<pre>"; print_r($cart); die;
		$amount = $cart->getOrderTotal(true,Cart::BOTH);
		$responseMsg1 = $_POST['RESPMSG'];
		$mode = Configuration::get('PayTM_MODE');
		if ($bool == "TRUE") {
			// Create an array having all required parameters for status query.
			$requestParamList = array("MID" => $merchant_id , "ORDERID" => $_POST['ORDERID']);
			
			$StatusCheckSum = getChecksumFromArray($requestParamList,$secret_key);
					
			$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
			//echo "<pre>"; print_r($requestParamList); die;
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
					if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$order_amount)
					{
						$status_code = "Ok";
						$message= $responseMsg1;
						$responseMsg= $responseMsg1;
				   		// $status = "15" ;
						$status = Configuration::get('Paytm_ID_ORDER_SUCCESS');
					}
					else{
						$responseMsg = "Transaction Failed. ";
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
			//echo "THIS"; die;
			$status_code = "Failed";
            $responseMsg = "Security Error ..!";
            $message = $responseMsg1;
            $status = Configuration::get('Paytm_ID_ORDER_FAILED');
        }
		$history_message = $responseMsg.'. Paytm Payment ID: '.$_POST['TXNID'];		
		$customer = new Customer((int)$this->context->cart->id_customer);
		$secure_key = Context::getContext()->customer->secure_key;
		$obj = new Paytm();
		
		$obj->validateOrder(intval($cart->id), $status, $order_amount, $obj->displayName, $history_message, $extras, '', false, $cart->secure_key);					
		$this->context->smarty->assign(array(
			'status' => $status_code,
			'responseMsg' => $message,
			'this_path' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));
		//$cart_qties == 0;
		$cart->delete();
		//$this->setTemplate('payment_response.tpl');
		//Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)$this->module->currentOrder);
		//$this->setTemplate('module:paytm/views/templates/front/payment_response.tpl');
		//Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
		
		if ($order_id && ($secure_key == $customer->secure_key) && $responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$order_amount) {
            $module_id = $this->module->id;
            Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$_POST['ORDERID'].'&key='.$customer->secure_key.'&responseMsg='.$message);
        } else {
            //$this->errors[] = $this->module->l('An error occured. Please contact the merchant to have more informations');
            //return $this->setTemplate('module:paytm/views/templates/hook/front/error.tpl');
			//return $this->display(__FILE__, 'views/templates/hook/front/error.tpl');
			$this->setTemplate('module:paytm/views/templates/hook/payment_response.tpl');
        }
		
		
		//Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$_POST['ORDERID'].'&key='.$customer->secure_key.'&responseMsg='.$message);
		
		
		//$this->setTemplate('module:paytm/views/templates/hook/payment_response.tpl');
		
		
		/* $url = $this->context->link->getModuleLink('paytm', 'paymentReturn', array(
            'secure_key' => $this->context->customer->secure_key), true);
        //PrestaShopLogger::addLog('rediret to payment return : '.$url, 1, null, null, null, true);
        Tools::redirect($url);
        exit; */
}
}
