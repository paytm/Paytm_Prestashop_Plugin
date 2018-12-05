<?php 	
// error_reporting(E_ALL);	

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
	exit;
}

require_once(dirname(__FILE__).'/lib/encdec_paytm.php');

class paytm extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();

	private $_title;
	
	function __construct()
	{		
		$this->name = 'paytm';
		$this->tab = 'payments_gateways';
		$this->version = 3.0;
		$this->author = 'Paytm Development Team';
				
		parent::__construct();

		$this->displayName = $this->l('Paytm');
		$this->description = $this->l('Accept payments by Paytm');
		
		$this->page = basename(__FILE__, '.php');
	}
	

	public function getDefaultCallbackUrl(){
		return $this->context->link->getModuleLink($this->name, 'validation');
	}

	public function install()
	{
		if(parent::install()){

			Configuration::updateValue("Paytm_MERCHANT_ID", "");
			Configuration::updateValue("Paytm_MERCHANT_KEY", "");
			Configuration::updateValue("Paytm_TRANSACTION_STATUS_URL", "");
			Configuration::updateValue("Paytm_GATEWAY_URL", "");
			Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", "");
			Configuration::updateValue("Paytm_MERCHANT_CHANNEL_ID", "WEB");
			Configuration::updateValue("Paytm_MERCHANT_WEBSITE", "");
			Configuration::updateValue("Paytm_CALLBACK_URL_STATUS", 0);
			Configuration::updateValue("Paytm_CALLBACK_URL", $this->getDefaultCallbackUrl());			
			Configuration::updateValue("Paytm_PROMO_CODE_STATUS", 0);
			Configuration::updateValue("Paytm_PROMO_CODE_VALIDATION", 1);
			Configuration::updateValue("Paytm_PROMO_CODES", "");
			
			$this->registerHook('paymentOptions');
			$this->registerHook('displayPaymentByBinaries');
			if(!Configuration::get('Paytm_ORDER_STATE')){
				$this->setPaytmOrderState('Paytm_ID_ORDER_SUCCESS','Payment Received','#b5eaaa');
				$this->setPaytmOrderState('Paytm_ID_ORDER_FAILED','Payment Failed','#E77471');
				$this->setPaytmOrderState('Paytm_ID_ORDER_PENDING','Payment Pending','#F4E6C9');
				Configuration::updateValue('Paytm_ORDER_STATE', '1');
			}		
			return true;
		}
		else {
			return false;
		}
	
	}

	public function uninstall()
	{
		if (!Configuration::deleteByName("Paytm_MERCHANT_ID") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_KEY") OR 
			!Configuration::deleteByName("Paytm_TRANSACTION_STATUS_URL") OR 
			!Configuration::deleteByName("Paytm_GATEWAY_URL") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_INDUSTRY_TYPE") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_CHANNEL_ID") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_WEBSITE") OR 
			!Configuration::deleteByName("Paytm_CALLBACK_URL_STATUS") OR 
			!Configuration::deleteByName("Paytm_CALLBACK_URL") OR 
			!Configuration::deleteByName("Paytm_PROMO_CODE_STATUS") OR 
			!Configuration::deleteByName("Paytm_PROMO_CODE_VALIDATION") OR 
			!Configuration::deleteByName("Paytm_PROMO_CODES") OR 
			!parent::uninstall()) {
			return false;
		}
		return true;
	}


	public function setPaytmOrderState($var_name,$status,$color){
		$orderState = new OrderState();
		$orderState->name = array();
		foreach(Language::getLanguages() AS $language){
			$orderState->name[$language['id_lang']] = $status;
		}
		$orderState->send_email = false;
		$orderState->color = $color;
		$orderState->hidden = false;
		$orderState->delivery = false;
		$orderState->logable = true;
		$orderState->invoice = true;
		if ($orderState->add())
			Configuration::updateValue($var_name, (int)$orderState->id);
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
			} else {
				$url_parts = parse_url($_POST["callback_url"]);
				if(!isset($url_parts["scheme"]) || (strtolower($url_parts["scheme"]) != "http" 
					&& strtolower($url_parts["scheme"]) != "https") || !isset($url_parts["host"]) || $url_parts["host"] == ""){
					$this->_postErrors[] = $this->l('Callback URL is invalid. Please enter valid URL and it must be start with http:// or https://');
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
				Configuration::updateValue("Paytm_PROMO_CODE_STATUS", $_POST["promo_code_status"]);
				Configuration::updateValue("Paytm_PROMO_CODE_VALIDATION", $_POST["promo_code_validation"]);
				Configuration::updateValue("Paytm_PROMO_CODES", $_POST["promo_codes"]);
				$this->displayConf();
			} else {
				$this->displayErrors();
			}
		}

		$this->_displayPaytm();
		$this->_displayFormSettings();
		return $this->_html;
    }

    public function displayConf(){
		$this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
	}
	
	public function displayErrors(){
		$nbErrors = sizeof($this->_postErrors);
		$this->_html .= '
		<div class="alert error">
			<h3>'.($nbErrors > 1 ? $this->l('There are') : $this->l('There is')).' '.$nbErrors.' '.($nbErrors > 1 ? $this->l('errors') : $this->l('error')).'</h3>
			<ol>';
		foreach ($this->_postErrors AS $error)
			$this->_html .= '<li>'.$error.'</li>';
		$this->_html .= '
			</ol>
		</div>';
	}
	
	public function _displayPaytm(){
		$this->_html .= '
		<img src="../modules/paytm/logo.png" style="float:left; padding: 0px; margin-right:15px;" />
		<b>'.$this->l('This module allows you to accept payments by Paytm.').'</b><br /><br />
		'.$this->l('If the client chooses this payment mode, your Paytm account will be automatically credited.').'<br />
		'.$this->l('You need to configure your Paytm account first before using this module.').'
		<br /><br /><br />';
	}

	// admin settings
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

		$promo_codes = isset($_POST["promo_codes"])? 
							$_POST["promo_codes"] : Configuration::get("Paytm_PROMO_CODES");

		$last_updated = "";
		$path = __DIR__."/paytm_version.txt";
		if(file_exists($path)){
			$handle = fopen($path, "r");
			if($handle !== false){
				$date = fread($handle, 10); // i.e. DD-MM-YYYY or 25-04-2018
				$last_updated = '<p>Last Updated: '. date("d F Y", strtotime($date)) .'</p>';
			}
		}
		
		$footer_text = '<hr/><div class="text-center">'.$last_updated.'<p>Prestashop Version: '. _PS_VERSION_ .'</p></div><hr/>';

		$this->bootstrap = true;
		$this->_html .= '
			<div id="paytm_config" class="panel panel-default">
				<div class="panel-body">
					<form id="module_form" class="defaultForm form-horizontal" method="POST" novalidate="">
						<ul class="nav nav-tabs">
							<li class="active"><a href="#tab-general" data-toggle="tab">'.$this->l("General").'</a></li>
							<li><a href="#tab-promo-code" data-toggle="tab">'.$this->l("Promo Code").'</a></li>
						</ul>
						<div class="tab-content">
							<div class="tab-pane active" id="tab-general">
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
									<div class="form-group">
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

									<div class="row-fluid">
										<div class="pull-right btn btn-primary" onclick="switchToTab(\'tab-promo-code\');"><i class="process-icon-next"></i>'. $this->l('Next') .'</div>
									</div>
								</div>
							</div>
							<div class="tab-pane" id="tab-promo-code">
								<div class="form-wrapper">
									<div class="form-group">
										<label class="control-label col-sm-3" for="promo_code_status">
											'.$this->l("Promo Code Status").'
										</label>
										<div class="col-sm-9">
											<select name="promo_code_status" id="promo_code_status" class="form-control">
												<option value="1" '.(Configuration::get("Paytm_PROMO_CODE_STATUS") == "1"? "selected" : "").'>'.$this->l('Enable').'</option>
												<option value="0" '.(Configuration::get("Paytm_PROMO_CODE_STATUS") == "0"? "selected" : "").'>'.$this->l('Disable').'</option>
											</select>
											<span><b>'. $this->l("Enabling this will show Promo Code field at Checkout.") .'</b></span>
										</div>
									</div>

									<div class="form-group">
										<label class="control-label col-sm-3" for="promo_code_validation">
											<span data-toggle="tooltip" title="'. $this->l("Validate applied Promo Code before proceeding to Paytm payment page.") . '">'.$this->l("Local Validation").'</span>
										</label>
										<div class="col-sm-9">
											<select name="promo_code_validation" id="promo_code_validation" class="form-control">
												<option value="1" '.(Configuration::get("Paytm_PROMO_CODE_VALIDATION") == "1"? "selected" : "").'>'.$this->l('Enable').'</option>
												<option value="0" '.(Configuration::get("Paytm_PROMO_CODE_VALIDATION") == "0"? "selected" : "").'>'.$this->l('Disable').'</option>
											</select>
											<span><b>'. $this->l("Transaction will be failed in case of Promo Code failure at Paytm's end.") .'</b></span>
										</div>
									</div>


									<div class="form-group">
										<label class="control-label col-lg-3">
											<span data-toggle="tooltip" title="'. $this->l("These promo codes must be configured with your Paytm MID.") . '">'.$this->l("Promo Codes").'</span>
										 </label>
										<div class="col-lg-9">
											<input type="text" name="promo_codes" value="' . $promo_codes . '"  class="" placeholder="'.$this->l("Enter promo codes here").'"/>
											<span><b>'. $this->l("Use comma ( , ) to separate multiple codes") .'<i> i.e. FB50,CASHBACK10</i> etc.</b></span>
										</div>
									</div>

									<div class="form-group">
										<button type="submit" value="1" id="module_form_submit_btn" name="submitPaytm" class="btn btn-primary pull-right">
											<i class="process-icon-save"></i> Save
										</button>
									</div>
								</div>
							</div>
							
						</div>
					</form>
				</div>
			</div>
			'.$footer_text.'
			<style>
			#paytm_config label.control-label span:after {
			    font-family: FontAwesome;
			    color: #1E91CF;
			    content: "\f059";
			    margin-left: 4px;
			}
			#paytm_config ul.nav-tabs {
				margin-bottom: 20px !important;
				border-bottom: 1px solid #ddd;
			}
			#paytm_config .nav-tabs > li.active > a, #paytm_config .nav-tabs > li.active > a:hover, #paytm_config .nav-tabs > li.active > a:focus {
				font-weight: bold;
				color: #333;
			}
			</style>
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

			function switchToTab(tab_name){
				$(\'.nav-tabs a[href="#\'+tab_name+\'"]\').tab(\'show\');
			}

			$(document).on("change", "select[name=\"callback_url_status\"]", function(){
				toggleCallbackUrl();
			});
			toggleCallbackUrl();
			
			$(document).ready(function(){
				$(\'[data-toggle="tooltip"]\').tooltip(); 
			});
			</script>';
	}

	public function hookdisplayPaymentByBinaries($params)
	{
		if (!$this->active) {
			return;
		}

		$btn = '';
		$btn = '<section class="js-payment-binary js-payment-paytm disabled">';
		$btn .= '<button type="button" onclick="document.getElementById(\'paytm_form_redirect\').submit();" class="btn btn-primary center-block">Pay with Paytm</button>';
		$btn .= '</section>';

		return $btn;
	}


	public function hookPaymentOptions($params)
	{
		if (!$this->active) {
			return;
		}
	
		$newOption = new PaymentOption();
		$newOption->setCallToActionText($this->l('Pay by Paytm'));
		$newOption->setForm($this->generateForm());
		$newOption->setBinary(true);
		$newOption->setModuleName('paytm');
		
		return [$newOption];
	}

	// frontend Paytm Form
	private function generateForm(){
		global $smarty, $cart;

		$bill_address = new Address(intval($cart->id_address_invoice));
		$customer = new Customer(intval($cart->id_customer));

		if (!Validate::isLoadedObject($bill_address) OR ! Validate::isLoadedObject($customer))
			return $this->l("Paytm error: (invalid address or customer)");


		$order_id = intval($cart->id);

		// $order_id = "TEST_" . strtotime("now") . "__" . $order_id; // just for testing

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


		// enable promo code interface either if local validation is disabled
		// or if validation is enabled and there is any promo code saved in database
		if(!Configuration::get("Paytm_PROMO_CODE_VALIDATION") || 
			(Configuration::get("Paytm_PROMO_CODE_VALIDATION") && Configuration::get("Paytm_PROMO_CODES") && trim(Configuration::get("Paytm_PROMO_CODES")) != "")) {
			$show_promo_code = true;
		} else {
			$show_promo_code = false;
		}


		$smarty->assign(
						array(
							"paytm_post" => $post_variables,
							"action" => Configuration::get("Paytm_GATEWAY_URL"),
							"show_promo_code" => $show_promo_code,
							"base_url" => Tools::getHttpHost(true).__PS_BASE_URI__,
							)
					);
		
		return $this->display(__FILE__, 'payment_form.tpl');
		// $this->setTemplate('module:paytm/views/templates/front/payment_form.tpl');
	}
}
?>