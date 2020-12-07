<?php
if (!defined("_PS_VERSION_"))
	exit;

require_once(dirname(__FILE__).'/lib/PaytmHelper.php');
require_once(dirname(__FILE__).'/lib/PaytmChecksum.php');

class Paytm extends PaymentModule {
	
	private $_html = "";
	private $_postErrors = array();

	public function __construct() {
		$this->name 		= PaytmConstants::PAYTM_PLUGIN_NAME;
		$this->tab 			= PaytmConstants::PAYTM_TAB;
		$this->version 		= PaytmConstants::PLUGIN_VERSION;
		$this->author 		= PaytmConstants::PAYTM_PLUGIN_AUTHOR;
	

		parent::__construct();
		$this->page         = basename(__FILE__, ".php");
		$this->displayName 	= $this->l(PaytmConstants::PAYTM_DISPLAYNAME);
		$this->description 	= $this->l(PaytmConstants::PAYTM_DESCRIPTION);
	}
    /**
	* get Default callback url
	*/
	private function getDefaultCallbackUrl(){
		if(!empty(PaytmConstants::CUSTOM_CALLBACK_URL)){
		    return PaytmConstants::CUSTOM_CALLBACK_URL;
		}else{
		    return $this->context->link->getModuleLink('paytm','response');
		}
	}

	public function install() {
		if (parent::install()) {
			Configuration::updateValue("Paytm_MERCHANT_ID", "");
			Configuration::updateValue("Paytm_MERCHANT_KEY", "");
			Configuration::updateValue("Paytm_ENVIRONMENT", "");
			Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", "");
			Configuration::updateValue("Paytm_MERCHANT_WEBSITE", "");

			$this->registerHook("payment");
			$this->registerHook("paymentReturn");
			$this->registerHook('displayAdminOrder');
			if (!Configuration::get("Paytm_ORDER_STATE")) {
				$this->setPaytmOrderState("Paytm_ID_ORDER_SUCCESS", PaytmConstants::PAYTM_PAYMENT_SUCCESS, "#b5eaaa");
				$this->setPaytmOrderState("Paytm_ID_ORDER_FAILED", PaytmConstants::PAYTM_PAYMENT_FAILED, "#E77471");
				$this->setPaytmOrderState("Paytm_ID_ORDER_PENDING", PaytmConstants::PAYTM_PAYMENT_PENDING, "#F4E6C9");
				Configuration::updateValue("Paytm_ORDER_STATE", "1");
			}
			$this->install_db();
			return true;
		} else {
			return false;
		}
	}

	public function uninstall() {

		if (!Configuration::deleteByName("Paytm_MERCHANT_ID") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_KEY") OR  
			!Configuration::deleteByName("Paytm_ENVIRONMENT") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_INDUSTRY_TYPE") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_WEBSITE") OR 
			!parent::uninstall()) {
			return false;
		}
		
		$this->uninstall_db();
		
		return true;
	}

	public function setPaytmOrderState($var_name, $status, $color) {

		$orderState = new OrderState();
		$orderState->name = array();
		foreach (Language::getLanguages() AS $language) {
			$orderState->name[$language["id_lang"]] = $status;
		}
		$orderState->send_email		= false;
		$orderState->color			= $color;
		$orderState->hidden			= false;
		$orderState->delivery		= false;
		$orderState->logable		= true;
		$orderState->invoice		= true;
		if ($orderState->add())
			Configuration::updateValue($var_name, (int) $orderState->id);
			
		return true;

	}

	public function getContent() {

		$this->_html = "<h2>" . $this->displayName . "</h2>";
		if (isset($_POST["submitPaytm"])) {
			// trim all values
			foreach($_POST as &$v){
				$v = trim($v);
			}
			if (!isset($_POST["merchant_id"]) || $_POST["merchant_id"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Merchant ID.");
			}
			if (!isset($_POST["merchant_key"]) || $_POST["merchant_key"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Merchant Key.");
			}
			if (!isset($_POST["industry_type"]) || $_POST["industry_type"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Industry Type.");
			}
			if (!isset($_POST["website"]) || $_POST["website"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Website.");
			}
			if (!isset($_POST["paytm_environment"]) || $_POST["paytm_environment"] == "" || !in_array($_POST["paytm_environment"],array('0','1'))){
				$this->_postErrors[] = $this->l("Please Select Environment.");
			}
			if (!sizeof($this->_postErrors)) {
				if(!PaytmHelper::validateCurl(PaytmHelper::getPaytmURL(PaytmConstants::ORDER_STATUS_URL,Configuration::get('Paytm_ENVIRONMENT')))){
					$this->displayCurlerror();
				}else{
					Configuration::updateValue("Paytm_MERCHANT_ID", $_POST["merchant_id"]);
					Configuration::updateValue("Paytm_MERCHANT_KEY", $_POST["merchant_key"]);
					Configuration::updateValue("Paytm_ENVIRONMENT", $_POST["paytm_environment"]);
					Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", $_POST["industry_type"]);
					Configuration::updateValue("Paytm_MERCHANT_WEBSITE", $_POST["website"]);
					$this->displayConf();
				}
			} else {
				$this->displayErrors();
			}
		}
		$this->_displayPaytm();
		$this->_displayFormSettings();

		return $this->_html;
	}

    public function displayCurlerror(){

		$this->_html .='<div class="alert error">'.PaytmConstants::ERROR_CURL_WARNING.
		               '</div>';
	}

	public function displayConf() {

		$this->_html .= '<div class="conf confirm">
			                <img src="../img/admin/ok.gif" alt="' . $this->l("Confirmation") . '" />' . $this->l("Settings updated") . '
		                 </div>';
	}

	public function displayErrors() {

		$nbErrors = sizeof($this->_postErrors);
		$this->_html .= '
		<div class="alert error">
			<h3>' . ($nbErrors > 1 ? $this->l("There are") : $this->l("There is")) . ' ' . $nbErrors . ' ' . ($nbErrors > 1 ? $this->l("errors") : $this->l("error")) . '</h3>
			<ol>';
		    foreach ($this->_postErrors AS $error)
			  $this->_html .= "<li>" . $error . "</li>";
		      $this->_html .= '
			</ol>
		</div>';
	}

	public function _displayPaytm() {

		$this->_html .= '<img src="../modules/paytm/logo.png" style="float:left; padding: 0px; margin-right:15px;" />
		<b>' . $this->l("This module allows you to accept payments by Paytm.") . '</b><br /><br />' . $this->l("If the client chooses this payment mode, your Paytm account will be automatically credited.") . '<br />
		' . $this->l("You need to configure your Paytm account first before using this module. Please enter following details provided to you by Paytm.") . '
		<br /><br /><br />';
	}

	public function _displayFormSettings() {

		$field_value = array();
		$field_value = $this->getfieldvalues($_POST);

		//last updated time of paytm plugin
		$last_updated = date("d F Y", strtotime(PaytmConstants::LAST_UPDATED)) .' - '.PaytmConstants::PLUGIN_VERSION;	
		$curl_version = PaytmHelper::getcURLversion();

		$footer_text    = '<hr/>
		<div class="text-center">
		   <b>PHP Version:</b> '. PHP_VERSION .' |
		   <b>Curl Version:</b> '. $curl_version .' | 
		   <b>Prestashop Version:</b> '. _PS_VERSION_ .' | 
		   <b>Last Updated:</b> '.$last_updated.' | 
		   <a href="'.PaytmConstants::PLUGIN_DOC_URL.'" target="_blank">Developer Docs</a>
		</div>
	  <hr/>';

		$this->bootstrap = true;
		// $wait_msg='jQuery("body").block({
		// 	message: "'.__(PaytmConstants::POPUP_LOADER_TEXT).'",
		// 		overlayCSS: {
		// 			background: "#fff",
		// 			opacity: 0.6
		// 		}, css: {
		// 			padding: 20,
		// 			textAlign: "center",
		// 			color: "#555",
		// 			border: "3px solid #aaa",
		// 			backgroundColor: "#fff",
		// 			cursor: "wait",
		// 			lineHeight: "32px"
		// 		}
		// 	});';
		$this->_html .= '
			<form id="module_form" class="defaultForm form-horizontal" method="POST" novalidate="">
				<div class="panel">
					<div class="panel-heading">'.$this->l("Paytm Payment Configuration").'</div>
					<div class="form-wrapper">
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Merchant ID").'</label>
							<div class="col-lg-9">
								<input type="text" name="merchant_id" value="' . $field_value['merchant_id'] . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Merchant Key").'</label>
							<div class="col-lg-9">
								<input type="text" name="merchant_key" value="' . $field_value['merchant_key'] . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Website").'</label>
							<div class="col-lg-9">
								<input type="text" name="website" value="' . $field_value['website'] . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Industry Type").'</label>
							<div class="col-lg-9">
								<input type="text" name="industry_type" value="' . $field_value['industry_type'] . '"  class="" required="required"/>
							</div>
						</div>		
                        <div class="form-group">
						<label class="control-label col-lg-3 required"> '.$this->l("Environment").'</label>
								<div class="col-lg-9">
										<select name="paytm_environment" class="" required="required" >
										<option '.($field_value['paytm_environment'] != "1"? "selected" : "").' value="0" >Staging</option>
										<option '.($field_value['paytm_environment'] == "1"? "selected" : "").' value="1">Production</option>
										</select>
								</div>
						</div>
					</div>
					<div class="panel-footer">
					<button type="submit" value="1" id="module_form_submit_btn" name="submitPaytm" class="btn btn-default pull-right">
					<i class="process-icon-save"></i> Save
				</button>
			</div>
		</div>
	</form>
	'.$footer_text.'
   ';
	}

	public function getfieldvalues($data){

		$field_data = array();
		$field_data['merchant_id']        = isset($data["merchant_id"])?$data["merchant_id"] : Configuration::get("Paytm_MERCHANT_ID");
		$field_data['merchant_key']       = isset($data["merchant_key"])?$data["merchant_key"] : Configuration::get("Paytm_MERCHANT_KEY");
	    $field_data['industry_type']      = isset($data["industry_type"])?$data["industry_type"] : Configuration::get("Paytm_MERCHANT_INDUSTRY_TYPE");
		$field_data['website']            = isset($data["website"])?$data["website"] : Configuration::get("Paytm_MERCHANT_WEBSITE");
	    $field_data['paytm_environment'] = isset($data["paytm_environment"])?$data["paytm_environment"] : Configuration::get("Paytm_ENVIRONMENT");
		
		return $field_data;
								
	}

	public function hookPayment($params) {
		
		if(PaytmConstants::ONLY_SUPPORT_INR){
			$id_currency = intval(Configuration::get('PS_CURRENCY_DEFAULT'));
			$currency = new Currency(intval($id_currency));
			$currency_code =$currency->iso_code;
			   if($currency_code != 'INR'){
				 return false;
				}
			}
		global $smarty;
		$smarty->assign(array(
			"this_path" 	=> $this->_path,
			"this_path_ssl" => Configuration::get("PS_FO_PROTOCOL") . $_SERVER["HTTP_HOST"] . __PS_BASE_URI__ . "modules/{$this->name}/"));

		return $this->display(__FILE__, "payment.tpl");
	}

	public function execPayment($cart) {

		global $smarty, $cart;
		
		$bill_address 	= new Address(intval($cart->id_address_invoice));
		$customer 		= new Customer(intval($cart->id_customer));

		if (!Validate::isLoadedObject($bill_address) OR ! Validate::isLoadedObject($customer))
			return $this->l("Paytm error: (invalid address or customer)");

		$order_id = PaytmHelper::getPaytmOrderId(intval($cart->id));
		
		$cust_id = $email = $mobile_no = "";
		if(isset($bill_address->phone_mobile) && trim($bill_address->phone_mobile) != "")
			$mobile_no = preg_replace("#[^0-9]{0,13}#is", "", $bill_address->phone_mobile);

		if(isset($customer->email) && trim($customer->email) != "")
			$email = $customer->email;

		if(!empty($customer->email)){
			$cust_id = $email = trim($customer->email);
		} else if(!empty($cart->id_customer)){
			$cust_id = intval($cart->id_customer);
		}else{
			$cust_id = "CUST_".$order_id;
		}

				
		$amount         = $cart->getOrderTotal(true, Cart::BOTH);

		$paramData = array('amount' => $amount, 'order_id' => $order_id, 'cust_id' => $cust_id, 'email' => $email, 'mobile_no' => $mobile_no);
		$checkout_url          = str_replace('MID',Configuration::get("Paytm_MERCHANT_ID"), PaytmHelper::getPaytmURL(PaytmConstants::CHECKOUT_JS_URL, Configuration::get('Paytm_ENVIRONMENT')));
		$data                  = $this->blinkCheckoutSend($paramData);
		

		if(!empty($data['txnToken'])){
			$txn_token             = $data['txnToken'];
			$paytm_message         = PaytmConstants::TOKEN_GENERATED_SUCCESS;
		}else{
			$txn_token             = '';
			$paytm_message         =  PaytmConstants::TEXT_RESPONSE_ERROR;

		}

		$smarty->assign(array(
							"paytm_post" 	=> $paramData,
							"ORDER_ID"      => $order_id,
							"checkout_url"  => $checkout_url,
							"txn_token"     => $txn_token,
							"messsage"		=> $paytm_message,
			                "CUST_ID"       => $cust_id));

		return $this->display(__FILE__, "payment_execution.tpl");

	}

	public function hookPaymentReturn($params) {

		if (!$this->active)
			return;

		$state = $params["objOrder"]->getCurrentState();
		if ($state == Configuration::get("Paytm_ID_ORDER_SUCCESS")) {
			$this->smarty->assign(array(
				"status"   => "ok",
				"id_order" => $params["objOrder"]->id
			));
		} else
			$this->smarty->assign("status", "failed");

		return $this->display(__FILE__, "payment_return.tpl");

	}
	/**
	* create paytm_order_data table.
	*/
	private function install_db() {
		Db::getInstance()->execute("
			CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "paytm_order_data` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`order_id` int(11) NOT NULL,
				`paytm_order_id` VARCHAR(255) NOT NULL,
				`transaction_id` VARCHAR(255) NOT NULL,
				`status` ENUM('0', '1')  DEFAULT '0' NOT NULL,
				`paytm_response` TEXT,
				`date_added` DATETIME NOT NULL,
				`date_modified` DATETIME NOT NULL,
				PRIMARY KEY (`id`)
			);");
	}
	/**
	* drop paytm_order_data table.
	*/
	private function uninstall_db() {
		Db::getInstance()->execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "paytm_order_data`;");
	}
	
	public function hookDisplayAdminOrder($params)
	{    
		$id_order         = $params['id_order'];
		$paytm_order_data = $this->getPaytmOrderData($id_order);

		if($paytm_order_data){
			$data['transaction_id']			= $paytm_order_data['transaction_id'];
			$data['paytm_order_id']			= $paytm_order_data['paytm_order_id'];
			$data['order_data_id']			= $paytm_order_data['id'];
			$data['paytm_response'] 		= json_decode($paytm_order_data['paytm_response'],true);

		$this->context->controller->addCSS(array($this->_path.'views/css/paytm.css'));
							  
		$this->context->smarty->assign(array(
					"paytm_value" => $data));

		return $this->display(__FILE__, 'views/templates/hook/paytm_order.tpl');
		}
	}

	public function getPaytmOrderData($order_id) {

		$query = "SELECT * FROM " . _DB_PREFIX_ . "paytm_order_data WHERE order_id = '" . (int)$order_id . "' ORDER BY id DESC";
	    $result = Db::getInstance()->getRow($query);
	    if ($result != false) {
			return $result;
	    }
		return 0;
    }
   	/**
	* ajax - fetch and save transaction status in db
	*/
	public function savetxnstatus() {

		 $json = array("success" => false, "response" => '', 'message'=>PaytmConstants::TEXT_RESPONSE_ERROR);

	    if(!empty($_POST['paytm_order_id'])){

		 		$reqParams = array(
		 			"MID" 		=> Configuration::get("Paytm_MERCHANT_ID"),
		 			"ORDERID" 	=> $_POST['paytm_order_id']
		 		);

			$reqParams['CHECKSUMHASH'] = PaytmChecksum::generateSignature($reqParams, Configuration::get("Paytm_MERCHANT_KEY"));	

			/* number of retries untill cURL gets success */	
		 		$retry = 1;
		 		do{
					$resParams=PaytmHelper::executecUrl(PaytmHelper::getPaytmURL(PaytmConstants::ORDER_STATUS_URL,Configuration::get('Paytm_ENVIRONMENT')), $reqParams);
		 			$retry++;
		 		   }while(!$resParams['STATUS'] && $retry < PaytmConstants::MAX_RETRY_COUNT);

			    if(PaytmConstants::SAVE_PAYTM_RESPONSE && !empty($resParams['STATUS'])){
		 			$update_response	=	$this->saveTxnResponse($resParams, $_POST['order_data_id']); 
		 			if($update_response){

		 				$message = PaytmConstants::TEXT_RESPONSE_SUCCESS;
		 				if($resParams['STATUS'] != 'PENDING'){
							$message .= sprintf(PaytmConstants::TEXT_RESPONSE_STATUS_SUCCESS, $resParams['STATUS']);
						}						
		 				$json = array("success" => true, "response" => $update_response, 'message' => $message);
		 			}
		 		}
			}	
				
		return json_encode($json);
	}
	public function saveTxnResponse($data  = array(), $id = false){

		if(empty($data['STATUS'])) return false;

		$status 			= (!empty($data['STATUS']) && $data['STATUS'] =='TXN_SUCCESS') ? 1 : 0;
		$paytm_order_id 	= (!empty($data['ORDERID'])? $data['ORDERID']:'');
		$transaction_id 	= (!empty($data['TXNID'])? $data['TXNID']:'');

		if($paytm_order_id && $id){

			$sql = "SELECT * from " . _DB_PREFIX_ . "paytm_order_data WHERE paytm_order_id = '" . $paytm_order_id . "'";
			$query =  Db::getInstance()->getRow($sql);
			if($query){

				$update_response = (array)json_decode($query['paytm_response']);
				$update_response['STATUS'] 		= $data['STATUS'];
				$update_response['RESPCODE'] 	= $data['RESPCODE'];
				$update_response['RESPMSG'] 	= $data['RESPMSG'];

				$sql =  "UPDATE " . _DB_PREFIX_ . "paytm_order_data SET transaction_id = '" . $transaction_id . "', status = '" . (int)$status . "', paytm_response = '" . json_encode($update_response) . "', date_modified = NOW() WHERE paytm_order_id = '" . $paytm_order_id . "' AND id = '" . (int)$id . "'";
				Db::getInstance()->execute($sql);
				return $update_response;
			}			
		}		
		return false;
	}
	private function blinkCheckoutSend($paramData = array()){
		$apiURL = PaytmHelper::getPaytmURL(PaytmConstants::INITIATE_TRANSACTION_URL, Configuration::get('Paytm_ENVIRONMENT')) . '?mid='.Configuration::get('Paytm_MERCHANT_ID').'&orderId='.$paramData['order_id'];
	   $paytmParams = array();

	   $paytmParams["body"] = array(
		   "requestType"   => "Payment",
		   "mid"           => Configuration::get('Paytm_MERCHANT_ID'),
		   "websiteName"   => Configuration::get("Paytm_MERCHANT_WEBSITE"),
		   "orderId"       => $paramData['order_id'],
		   "callbackUrl"   => $this->getDefaultCallbackUrl(),
		   "txnAmount"     => array(
			   "value"     => strval($paramData['amount']),
			   "currency"  => "INR",
		   ),
		   "userInfo"      => array(
			   "custId"    => $paramData['cust_id'],
		   ),
	   );

	   /*
	   * Generate checksum by parameters we have in body
	   * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
	   */
	   $checksum = PaytmChecksum::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), Configuration::get("Paytm_MERCHANT_KEY"));

	   $paytmParams["head"] = array(
		   "signature"	=> $checksum
	   );
	   //print_r($paytmParams);

	   $response = PaytmHelper::executecUrl($apiURL, $paytmParams);
	   $data = array('orderId' => $paramData['order_id'], 'amount' => $paramData['amount']);
	   if(!empty($response['body']['txnToken'])){
		   $data['txnToken'] = $response['body']['txnToken'];
	   }else{
		   $data['txnToken'] = '';
	   }
	   $data['apiurl'] = $apiURL;
	   return $data;
   }
	/* 
	* Get the transaction token
	*/
	public function getTxnToken($order_id,$amount,$customer_id)
	{
		$txntoken="";
		if(!empty($amount) && (int)$amount > 0)
		{
			/* body parameters */
			$paytmParams["body"] = array(
				"requestType" => "Payment",
				"mid" => Configuration::get('Paytm_MERCHANT_ID'),
				"websiteName" => Configuration::get("Paytm_MERCHANT_WEBSITE"),
				"orderId" => $order_id,
				"callbackUrl" => $this->getDefaultCallbackUrl(),
				"txnAmount" => array(
					"value" => $amount,
					"currency" => "INR",
				),
				"userInfo" => array(
					"custId" => $customer_id,
				),
			);
			
			$checksum = PaytmChecksum::generateSignature(json_encode($paytmParams["body"], JSON_UNESCAPED_SLASHES), Configuration::get("Paytm_MERCHANT_KEY")); 
			
			$paytmParams["head"] = array(
				"signature"	=> $checksum
			);
			
			/* prepare JSON string for request */
			$post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
			$url = trim(PaytmHelper::getInitiateURL(Configuration::get('Paytm_ENVIRONMENT')))."/theia/api/v1/initiateTransaction?mid=".$paytmParams["body"]['mid']."&orderId=".$paytmParams["body"]['orderId'];
			
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json")); 
			$response = curl_exec($ch);
			$res = json_decode($response,true);  
			if(!empty($res['body']['resultInfo']['resultStatus']) && $res['body']['resultInfo']['resultStatus'] == 'S'){
				$txntoken = $res['body']['txnToken'];
			}
			//$txntoken = $post_data.json_encode($res);
		}
		return $txntoken;
	}
}
	
?>