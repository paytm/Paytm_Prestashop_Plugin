<?php 	
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
	exit;
}

require_once(dirname(__FILE__).'/lib/PaytmHelper.php');
require_once(dirname(__FILE__).'/lib/PaytmChecksum.php');


class paytm extends PaymentModule
{	
	private $_html = '';
	private $_postErrors = array();
	private $_title;
	
	function __construct()
	{		
		$this->name 		= PaytmConstants::PAYTM_PLUGIN_NAME;
		$this->tab 			= PaytmConstants::PAYTM_TAB;
		$this->version 		= PaytmConstants::PLUGIN_VERSION;
		$this->author 		= PaytmConstants::PAYTM_PLUGIN_AUTHOR;
	
		parent::__construct();
		$this->displayName 	= $this->l(PaytmConstants::PAYTM_DISPLAYNAME);
		$this->description 	= $this->l(PaytmConstants::PAYTM_DESCRIPTION);
		$this->page 		= basename(__FILE__, '.php');
	}
	
    /**
	* get Default callback url
	*/
	public function getDefaultCallbackUrl(){
		if(!empty(PaytmConstants::CUSTOM_CALLBACK_URL)){
			return PaytmConstants::CUSTOM_CALLBACK_URL;
		}else{
			return $this->context->link->getModuleLink($this->name, 'validation');
		}	
	
	}
	public function install()
	{
		if(parent::install()){

			Configuration::updateValue("Paytm_MERCHANT_ID", "");
			Configuration::updateValue("Paytm_MERCHANT_KEY", "");
			Configuration::updateValue("Paytm_ENVIRONMENT", "");
			Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", "");
			Configuration::updateValue("Paytm_MERCHANT_WEBSITE", "");			
			
			$this->registerHook('paymentOptions');
			$this->registerHook('displayPaymentByBinaries');
			$this->registerHook('displayAdminOrder');
			if(!Configuration::get('Paytm_ORDER_STATE')){
				$this->setPaytmOrderState('Paytm_ID_ORDER_SUCCESS',PaytmConstants::PAYTM_PAYMENT_SUCCESS,'#b5eaaa');
				$this->setPaytmOrderState('Paytm_ID_ORDER_FAILED',PaytmConstants::PAYTM_PAYMENT_FAILED,'#E77471');
				$this->setPaytmOrderState('Paytm_ID_ORDER_PENDING',PaytmConstants::PAYTM_PAYMENT_PENDING,'#F4E6C9');
				Configuration::updateValue('Paytm_ORDER_STATE', '1');
			}	
			
			
			$this->install_db();
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
			!Configuration::deleteByName("Paytm_ENVIRONMENT") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_INDUSTRY_TYPE") OR 
			!Configuration::deleteByName("Paytm_MERCHANT_WEBSITE") OR  
			!parent::uninstall()) {
			return false;
		}
	$this->uninstall_db();
		return true;
	}
	public function setPaytmOrderState($var_name,$status,$color){
		$orderState       = new OrderState();
		$orderState->name = array();
		foreach(Language::getLanguages() AS $language){
			$orderState->name[$language['id_lang']] = $status;
		}
		$orderState->send_email   = false;
		$orderState->color        = $color;
		$orderState->hidden       = false;
		$orderState->delivery     = false;
		$orderState->logable      = true;
		$orderState->invoice      = true;
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
			if (!isset($_POST["website"]) || $_POST["website"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Website.");
			}
            if (!isset($_POST["paytm_environment"]) || $_POST["paytm_environment"] == "" || !in_array($_POST["paytm_environment"],array('0','1'))){
				$this->_postErrors[] = $this->l("Please Select Environment.");
			}

			if (!sizeof($this->_postErrors)) {
				if(!PaytmHelper::validateCurl(PaytmHelper::getTransactionStatusURL($_POST["paytm_environment"]))){
					$this->displayCurlerror();
				}else{
				Configuration::updateValue("Paytm_MERCHANT_ID", $_POST["merchant_id"]);
				Configuration::updateValue("Paytm_MERCHANT_KEY", $_POST["merchant_key"]);
				Configuration::updateValue("Paytm_ENVIRONMENT", $_POST["paytm_environment"]);
				Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", $_POST["industry_type"]);
				Configuration::updateValue("Paytm_MERCHANT_WEBSITE", $_POST["website"]);
				
				$this->saveConfmessage();
				}
			} else {
				$this->displayErrors();
			}
		}
		$this->_displayPaytm();
		$this->_displayFormSettings();

		return $this->_html;
	}
	
    public function saveConfmessage(){

		$this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
	}

	public function displayCurlerror(){

		$this->_html .='<div class="alert error">'.PaytmConstants::ERROR_CURL_WARNING.                   '</div>';
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

		$field_value    = array();
		$field_value    = $this->getfieldvalues($_POST);
		//last updated time of paytm plugin
		$last_updated   = date("d F Y", strtotime(PaytmConstants::LAST_UPDATED)) .' - '.PaytmConstants::PLUGIN_VERSION;
		// Check cUrl is enabled or not	
		$curl_version   = PaytmHelper::getcURLversion();

		$footer_text    = '<hr/>
							 <div class="text-center">
							    <b>PHP Version:</b> '. PHP_VERSION .' |
								<b>Curl Version:</b> '. $curl_version .' | 
								<b>Prestashop Version:</b> '. _PS_VERSION_ .' | 
								<b>Last Updated:</b> '.$last_updated.' | 
								<a href="'.PaytmConstants::PLUGIN_DOC_URL.'" target="_blank" >Developer Docs</a>
							 </div>
						   <hr/>';
	
		$this->bootstrap = true;
		$this->_html .= '
			<div id="paytm_config" class="panel panel-default">
				<div class="panel-body">
					<form id="module_form" class="defaultForm form-horizontal" method="POST" novalidate="">
						<ul class="nav nav-tabs">
							<li class="active"><a href="#tab-general" data-toggle="tab">'.$this->l("General").'</a></li>
						</ul>
						<div class="tab-content">
							<div class="tab-pane active" id="tab-general">
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
									<div class="row-fluid">
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
			</style>';
	}

	public function getfieldvalues($data){

		$field_data=array();

		$field_data['merchant_id']        = isset($data["merchant_id"])?$data["merchant_id"] : Configuration::get("Paytm_MERCHANT_ID");
	    $field_data['merchant_key']       = isset($data["merchant_key"])? $data["merchant_key"] : Configuration::get("Paytm_MERCHANT_KEY");
	    $field_data['industry_type']      = isset($data["industry_type"])?$data["industry_type"] : Configuration::get("Paytm_MERCHANT_INDUSTRY_TYPE");
		$field_data['website']            = isset($data["website"])?$data["website"] : Configuration::get("Paytm_MERCHANT_WEBSITE");
	    $field_data['paytm_environment']  = isset($data["paytm_environment"])? $data["paytm_environment"] : Configuration::get("Paytm_ENVIRONMENT");
								
		return $field_data;
								
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
		if(PaytmConstants::ONLY_SUPPORT_INR){
		$id_currency = intval(Configuration::get('PS_CURRENCY_DEFAULT'));
		$currency = new Currency(intval($id_currency));
		$currency_code =$currency->iso_code;
		   if($currency_code != 'INR'){
			 return false;
		    }
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
		$bill_address   = new Address(intval($cart->id_address_invoice));
		$customer       = new Customer(intval($cart->id_customer));
		if (!Validate::isLoadedObject($bill_address) OR ! Validate::isLoadedObject($customer))
			return $this->l("Paytm error: (invalid address or customer)");

		$order_id       = PaytmHelper::getPaytmOrderId(intval($cart->id));

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

		$parameters = array(
			"MID"              => Configuration::get("Paytm_MERCHANT_ID"),
			"ORDER_ID"         => $order_id,
			"CUST_ID"          => $cust_id,
			"TXN_AMOUNT"       => $amount,
			"CHANNEL_ID"       => PaytmConstants::CHANNEL_ID,
			"CALLBACK_URL"     => $this->getDefaultCallbackUrl(),
			"INDUSTRY_TYPE_ID" => Configuration::get("Paytm_MERCHANT_INDUSTRY_TYPE"),
			"WEBSITE"          => Configuration::get("Paytm_MERCHANT_WEBSITE"),
			"MOBILE_NO" 	   => $mobile_no,
			"EMAIL" 		   => $email,
		);

		$parameters["CHECKSUMHASH"] = PaytmChecksum::generateSignature($parameters, Configuration::get("Paytm_MERCHANT_KEY"));	

		$parameters["X-REQUEST-ID"] 	= PaytmConstants::X_REQUEST_ID._PS_VERSION_;
		            
		$smarty->assign(array(
							"paytm_post"      => $parameters,
							"action"          => PaytmHelper::getTransactionURL(Configuration::get("Paytm_ENVIRONMENT")),
							"base_url"        => Tools::getHttpHost(true).__PS_BASE_URI__,));
		
		return $this->display(__FILE__, 'payment_form.tpl');
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
    /*for paytm order refresh */
    public function hookDisplayAdminOrder($params)
	{    
		$id_order            = $params['id_order'];
		$paytm_order_data    = $this->getPaytmOrderData($id_order);

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
	/** return paytm details of order*/
	public function getPaytmOrderData($order_id) {

        $query = "SELECT * FROM " . _DB_PREFIX_ . "paytm_order_data WHERE order_id = '" . (int)$order_id . "' ORDER BY id DESC";
        $result = Db::getInstance()->getRow($query);
        if ($result != false) {
             return $result;
       }
        return false;
	}
		/**
	* ajax - fetch and save transaction status in db
	*/
	public function savetxnstatus() {

	 $json = array("success" => false, "response" => '', 'message' => PaytmConstants::TEXT_RESPONSE_ERROR);

	 if(!empty($_POST['paytm_order_id'])){

		 		$reqParams       = array(
		 			"MID" 		=> Configuration::get("Paytm_MERCHANT_ID"),
		 			"ORDERID" 	=> $_POST['paytm_order_id']
		 		);

				$reqParams['CHECKSUMHASH'] = PaytmChecksum::generateSignature($reqParams, Configuration::get("Paytm_MERCHANT_KEY"));		
				
		 		$retry = 1;
		 		do{
			       $resParams=PaytmHelper::executecUrl(PaytmHelper::getTransactionStatusURL(Configuration::get('Paytm_ENVIRONMENT')), $reqParams);
		 			$retry++;
		 		  } while(!$resParams['STATUS'] && $retry < PaytmConstants::MAX_RETRY_COUNT);

			           if(PaytmConstants::SAVE_PAYTM_RESPONSE && !empty($resParams['STATUS'])){
		 			             $update_response	 =	$this->saveTxnResponse($resParams, $_POST['order_data_id']); 
		 			             if($update_response){
		 				            $message     = PaytmConstants::TEXT_RESPONSE_SUCCESS;
		 				            if($resParams['STATUS'] != 'PENDING'){
							          $message .= sprintf(PaytmConstants::TEXT_RESPONSE_STATUS_SUCCESS,$resParams['STATUS']);
						              }						
		 				            $json= array("success" => true, "response" => $update_response, 'message' => $message);
		 			                 }  
		 		        }
		}		

		 return(json_encode($json));

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
}
?>
