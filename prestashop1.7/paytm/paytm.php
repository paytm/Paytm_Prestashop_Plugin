<?php 	
// error_reporting(E_ALL);	

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
	exit;
}

require_once(dirname(__FILE__).'/lib/encdec_paytm.php');

class Paytm extends PaymentModule
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
		$this->description = $this->l('Module for accepting payments by Paytm');
		
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
			
			$this->registerHook('paymentOptions');
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

		$last_updated = "";
		$path = __DIR__."/paytm_version.txt";
		$handle = fopen($path, "r");
		if($handle !== false){
			$date = fread($handle, 10); // i.e. DD-MM-YYYY or 25-04-2018
			$last_updated = '<div class="pull-left"><p>Last Updated: '. date("d F Y", strtotime($date)) .'</p></div>';
		}

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
					</div>
					<div class="panel-footer">
						<div>
							<button type="submit" value="1" id="module_form_submit_btn" name="submitPaytm" class="btn btn-default pull-right">
								<i class="process-icon-save"></i> Save
							</button>
						</div>
						'.$last_updated.'
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
			</script>';
	}


	public function hookPaymentOptions($params)
	{
		if (!$this->active) {
			return;
		}
	
		$newOption = new PaymentOption();

		$newOption->setCallToActionText($this->trans('Pay by Paytm', array(), 'Modules.Paytm.Shop'))
		//->setForm($paymentForm)
		->setLogo(_MODULE_DIR_.'paytm/views/img/paytm.png')
		//->setAdditionalInformation('your additional Information')
		->setAction($this->context->link->getModuleLink($this->name, 'payment'));

		$newOption->setModuleName('paytm');
		
		return [$newOption];
	}
}
?>