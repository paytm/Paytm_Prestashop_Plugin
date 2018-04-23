<?php
if (!defined("_PS_VERSION_"))
	exit;

require_once(dirname(__FILE__) . "/lib/encdec_paytm.php");

class Paytm extends PaymentModule {

	private $_html = "";
	private $_postErrors = array();
	private static $debug_log = false;

	public function __construct() {
		$this->name = "paytm";
		$this->tab = "payments_gateways";
		$this->version = "3.0";
		$this->author = "Paytm Development Team";

		parent::__construct();
		
		$this->page = basename(__FILE__, ".php");
		$this->displayName = $this->l("Paytm");
		$this->description = $this->l("Module for accepting payments by Paytm");
	}

	private function getDefaultCallbackUrl(){
		return $this->context->link->getModuleLink('paytm','response');
	}

	public function install() {
		if (parent::install()) {
			Configuration::updateValue("Paytm_MERCHANT_ID", "");
			Configuration::updateValue("Paytm_MERCHANT_KEY", "");
			Configuration::updateValue("Paytm_TRANSACTION_STATUS_URL", "");
			Configuration::updateValue("Paytm_GATEWAY_URL", "");
			Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", "");
			Configuration::updateValue("Paytm_MERCHANT_CHANNEL_ID", "WEB");
			Configuration::updateValue("Paytm_MERCHANT_WEBSITE", "");
			Configuration::updateValue("Paytm_CALLBACK_URL_STATUS", 0);
			Configuration::updateValue("Paytm_CALLBACK_URL", $this->getDefaultCallbackUrl());
			Configuration::updateValue("Paytm_ENABLE_LOG", 0);

			$this->registerHook("payment");
			$this->registerHook("paymentReturn");
			if (!Configuration::get("Paytm_ORDER_STATE")) {
				$this->setPaytmOrderState("Paytm_ID_ORDER_SUCCESS", "Payment Received", "#b5eaaa");
				$this->setPaytmOrderState("Paytm_ID_ORDER_FAILED", "Payment Failed", "#E77471");
				$this->setPaytmOrderState("Paytm_ID_ORDER_PENDING", "Payment Pending", "#F4E6C9");
				Configuration::updateValue("Paytm_ORDER_STATE", "1");
			}
			return true;
		} else {
			return false;
		}
	}

	public function uninstall() {

		if (!Configuration::deleteByName("Paytm_MERCHANT_ID") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_KEY") OR 
			!Configuration::deleteByName("Paytm_TRANSACTION_STATUS_URL") OR 
			!Configuration::deleteByName("Paytm_GATEWAY_URL") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_INDUSTRY_TYPE") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_CHANNEL_ID") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_WEBSITE") OR 
			!Configuration::deleteByName("Paytm_CALLBACK_URL_STATUS") OR 
			!Configuration::deleteByName("Paytm_CALLBACK_URL") OR 
			!Configuration::deleteByName("Paytm_ENABLE_LOG") OR 
			!parent::uninstall()) {
			return false;
		}

		return true;
	}

	public function setPaytmOrderState($var_name, $status, $color) {
		$orderState = new OrderState();
		$orderState->name = array();
		foreach (Language::getLanguages() AS $language) {
			$orderState->name[$language["id_lang"]] = $status;
		}
		$orderState->send_email = false;
		$orderState->color = $color;
		$orderState->hidden = false;
		$orderState->delivery = false;
		$orderState->logable = true;
		$orderState->invoice = true;
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

			if (!isset($_POST["channel_id"]) || $_POST["channel_id"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Channel ID.");
			}

			if (!isset($_POST["website"]) || $_POST["website"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Website.");
			}

			if (!isset($_POST["gateway_url"]) || $_POST["gateway_url"] == ""){
				$this->_postErrors[] = $this->l("Please Enter Gateway Url.");
			}

			if (!isset($_POST["status_url"]) || $_POST["status_url"] == ""){
				$this->_postErrors[] = $this->l("Please Enter Transaction Status URL .");
			}

			if (!isset($_POST["callback_url"]) || $_POST["callback_url"] == ""){
				$this->_postErrors[] = $this->l("Please Enter Callback URL.");
			}

			// if log is enabled then try to write log file else show permission error
			if (isset($_POST["log_enable"])  && !empty($_POST["log_enable"])){
				$writeable = Paytm::addLog("Log Enabled", __FILE__, __LINE__);
				if($writeable != true){
					$this->_postErrors[] = $this->l($res);
				}
			}

			if (!sizeof($this->_postErrors)) {
				Configuration::updateValue("Paytm_MERCHANT_ID", $_POST["merchant_id"]);
				Configuration::updateValue("Paytm_MERCHANT_KEY", $_POST["merchant_key"]);
				Configuration::updateValue("Paytm_GATEWAY_URL", $_POST["gateway_url"]);
				Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", $_POST["industry_type"]);
				Configuration::updateValue("Paytm_MERCHANT_CHANNEL_ID", $_POST["channel_id"]);
				Configuration::updateValue("Paytm_MERCHANT_WEBSITE", $_POST["website"]);
				Configuration::updateValue("Paytm_TRANSACTION_STATUS_URL", $_POST["status_url"]);
				Configuration::updateValue("Paytm_CALLBACK_URL_STATUS", $_POST["callback_url_status"]);
				Configuration::updateValue("Paytm_CALLBACK_URL", $_POST["callback_url"]);
				Configuration::updateValue("Paytm_ENABLE_LOG", $_POST["log_enable"]);
				$this->displayConf();
			} else {
				$this->displayErrors();
			}
		}

		$this->_displayPaytm();
		$this->_displayFormSettings();
		return $this->_html;
	}

	public function displayConf() {
		$this->_html .= '
		<div class="conf confirm">
			<img src="../img/admin/ok.gif" alt="' . $this->l("Confirmation") . '" />
			' . $this->l("Settings updated") . '
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
		$this->_html .= '
		<img src="../modules/paytm/logo.png" style="float:left; padding: 0px; margin-right:15px;" />
		<b>' . $this->l("This module allows you to accept payments by Paytm.") . '</b><br /><br />
		' . $this->l("If the client chooses this payment mode, your Paytm account will be automatically credited.") . '<br />
		' . $this->l("You need to configure your Paytm account first before using this module. Please enter following details provided to you by Paytm.") . '
		<br /><br /><br />';
	}

	public function _displayFormSettings() {

		$merchant_id = isset($_POST["merchant_id"])? 
							$_POST["merchant_id"] : Configuration::get("Paytm_MERCHANT_ID");

		$merchant_key = isset($_POST["merchant_key"])? 
							$_POST["merchant_key"] : Configuration::get("Paytm_MERCHANT_KEY");

		$industry_type = isset($_POST["industry_type"])? 
							$_POST["industry_type"] : Configuration::get("Paytm_MERCHANT_INDUSTRY_TYPE");

		$channel_id = isset($_POST["channel_id"])? 
							$_POST["channel_id"] : Configuration::get("Paytm_MERCHANT_CHANNEL_ID");

		$website = isset($_POST["website"])? 
							$_POST["website"] : Configuration::get("Paytm_MERCHANT_WEBSITE");

		$gateway_url = isset($_POST["gateway_url"])? 
							$_POST["gateway_url"] : Configuration::get("Paytm_GATEWAY_URL");

		$status_url = isset($_POST["status_url"])? 
							$_POST["status_url"] : Configuration::get("Paytm_TRANSACTION_STATUS_URL");

		$callback_url = isset($_POST["callback_url"])? 
							$_POST["callback_url"] : Configuration::get("Paytm_CALLBACK_URL");

		$this->bootstrap = true;
		$this->_html .= '
			<form id="module_form" class="defaultForm form-horizontal" method="POST" novalidate="">
				<div class="panel">
					<div class="panel-heading">'.$this->l("Paytm Payment Configuration").'</div>
					<div class="form-wrapper">
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Merchant ID").'</label>
							<div class="col-lg-9">
								<input type="text" name="merchant_id" value="' . $merchant_id . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Merchant Key").'</label>
							<div class="col-lg-9">
								<input type="text" name="merchant_key" value="' . $merchant_key . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Website").'</label>
							<div class="col-lg-9">
								<input type="text" name="website" value="' . $website . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Industry Type").'</label>
							<div class="col-lg-9">
								<input type="text" name="industry_type" value="' . $industry_type . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group hidden">
							<label class="control-label col-lg-3 required"> '.$this->l("Channel Id").'</label>
							<div class="col-lg-9">
								<input type="text" name="channel_id" value="' . $channel_id . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Transaction Url").'</label>
							<div class="col-lg-9">
								<input type="text" name="gateway_url" value="' . $gateway_url . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Transaction Status Url").'</label>
							<div class="col-lg-9">
								<input type="text" name="status_url" value="' . $status_url . '"  class="" required="required"/>
							</div>
						</div>

						<div class="form-group">
							<label class="control-label col-sm-3 required" for="callback_url_status">
								'.$this->l("Custom Callback Url").'
							</label>
							<div class="col-sm-9">
								<select name="callback_url_status" id="callback_url_status" class="form-control">
									<option value="1" '.(Configuration::get("Paytm_CALLBACK_URL_STATUS") == "1"? "selected" : "").'>'.$this->l('Enable').'</option>
									<option value="0" '.(Configuration::get("Paytm_CALLBACK_URL_STATUS") == "0"? "selected" : "").'>'.$this->l('Disable').'</option>
								</select>
							</div>
						</div>

						<div class="callback_url_group form-group">
							<label class="control-label col-sm-3 required" for="callback_url">
								'.$this->l("Callback URL").'
							</label>
							<div class="col-sm-9">
								<input type="text" name="callback_url" id="callback_url" value="'. $callback_url .'" class="form-control" '.(Configuration::get("Paytm_CALLBACK_URL_STATUS") == "0"? "readonly" : "").'/>
							</div>
						</div>

						<div class="form-group '.(self::$debug_log == false? "hidden" : "").'">
							<label class="control-label col-lg-3 required">'.$this->l("Enable Debug Log").'</label>
							<div class="col-lg-9">
								<div class="radio-inline">
									<label><input type="radio" name="log_enable" value="1" '.(Configuration::get("Paytm_ENABLE_LOG") == 1? "checked" : "").'>Yes</label>
								</div>
								<div class="radio-inline">
									<label><input type="radio" name="log_enable" value="0" '.(Configuration::get("Paytm_ENABLE_LOG") != 1? "checked" : "").'>No</label>
								</div>
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
			<script type="text/javascript">
			var default_callback_url = "'.$this->getDefaultCallbackUrl().'";

			function toggleCallbackUrl(){
				if($("select[name=\"callback_url_status\"]").val() == "1"){
					$(".callback_url_group").removeClass("hidden");
					$("input[name=\"callback_url\"]").prop("readonly", false);
				} else {
					$(".callback_url_group").addClass("hidden");
					$("#callback_url").val(default_callback_url);
					$("input[name=\"callback_url\"]").prop("readonly", true);
				}
			}

			$(document).on("change", "select[name=\"callback_url_status\"]", function(){
				toggleCallbackUrl();
			});
			toggleCallbackUrl();
			</script>
		';
	}

	public function hookPayment($params) {
		global $smarty;
		$smarty->assign(array(
			"this_path" => $this->_path,
			"this_path_ssl" => Configuration::get("PS_FO_PROTOCOL") . $_SERVER["HTTP_HOST"] . __PS_BASE_URI__ . "modules/{$this->name}/"));

		return $this->display(__FILE__, "payment.tpl");
	}

	public function execPayment($cart) {
		
		global $smarty, $cart;

		$bill_address = new Address(intval($cart->id_address_invoice));
		$customer = new Customer(intval($cart->id_customer));

		if (!Validate::isLoadedObject($bill_address) OR ! Validate::isLoadedObject($customer))
			return $this->l("Paytm error: (invalid address or customer)");


		$order_id = intval($cart->id);

		// $order_id = "RHL_" . strtotime("now") . "__" . $order_id; // just for testing

		$amount = $cart->getOrderTotal(true, Cart::BOTH);

		$post_variables = array(
			"MID" => Configuration::get("Paytm_MERCHANT_ID"),
			"ORDER_ID" => $order_id,
			"CUST_ID" => intval($cart->id_customer),
			"TXN_AMOUNT" => $amount,
			"CHANNEL_ID" => Configuration::get("Paytm_MERCHANT_CHANNEL_ID"),
			"INDUSTRY_TYPE_ID" => Configuration::get("Paytm_MERCHANT_INDUSTRY_TYPE"),
			"WEBSITE" => Configuration::get("Paytm_MERCHANT_WEBSITE"),
		);

		if(isset($bill_address->phone_mobile) && trim($bill_address->phone_mobile) != "")
			$post_variables["MOBILE_NO"] = preg_replace("#[^0-9]{0,13}#is", "", $bill_address->phone_mobile);

		if(isset($customer->email) && trim($customer->email) != "")
			$post_variables["EMAIL"] = $customer->email;

		if (Configuration::get("Paytm_CALLBACK_URL_STATUS") == "0")
			$post_variables["CALLBACK_URL"] = $this->getDefaultCallbackUrl();
		else
			$post_variables["CALLBACK_URL"] = Configuration::get("Paytm_CALLBACK_URL");


		$post_variables["CHECKSUMHASH"] = getChecksumFromArray($post_variables, Configuration::get("Paytm_MERCHANT_KEY"));


		/* make log for all payment request */
		if(Configuration::get('Paytm_ENABLE_LOG')){
			$log_entry = "Request Type: Process Transaction (DEFAULT)". PHP_EOL;
			$log_entry .= "Request URL: " . Configuration::get("Paytm_GATEWAY_URL") . PHP_EOL;
			$log_entry .= "Request Params: " . print_r($post_variables, true) .PHP_EOL.PHP_EOL;
			Paytm::addLog($log_entry, __FILE__, __LINE__);
		}
		/* make log for all payment request */

		$smarty->assign(
						array(
							"paytm_post" => $post_variables,
							"action" => Configuration::get("Paytm_GATEWAY_URL")
							)
					);

		return $this->display(__FILE__, "payment_execution.tpl");
	}

	public function hookPaymentReturn($params) {
		if (!$this->active)
			return;

		$state = $params["objOrder"]->getCurrentState();
		if ($state == Configuration::get("Paytm_ID_ORDER_SUCCESS")) {
			$this->smarty->assign(array(
				"status" => "ok",
				"id_order" => $params["objOrder"]->id
			));
		} else
			$this->smarty->assign("status", "failed");
		return $this->display(__FILE__, "payment_return.tpl");
	}


	public static function addLog($message, $file = null, $line = null){

		// if log is disabled by module itself then return true to pretend everything working fine
		if(self::$debug_log == false){
			return true;
		}

		try {
			
			$log_file = __DIR__."/paytm.log";
			$handle = fopen($log_file, "a+");
			
			// if there is some permission issue
			if($handle == false){
				return "Unable to write log file (".$log_file."). Please provide appropriate permission to enable log.";
			}

			// append Indian Standard Time for each log
			$date = new DateTime();
			$date->setTimeZone(new DateTimeZone("Asia/Kolkata"));
			$log_entry = $date->format('Y-m-d H:i:s')."(IST)".PHP_EOL;

			if($file && $line){
				$log_entry .= $file."#".$line.PHP_EOL;
			} else if($file){
				$log_entry .= $file.PHP_EOL;
			} else if($line){
				$log_entry .= $line.PHP_EOL;
			}

			$log_entry .= $message.PHP_EOL.PHP_EOL;

			fwrite($handle, $log_entry);
			fclose($handle);

		} catch(Exception $e){

		}

		return true;
	}

}
?>