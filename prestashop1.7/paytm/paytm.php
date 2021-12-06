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
				if(!PaytmHelper::validateCurl(PaytmHelper::getPaytmURL(PaytmConstants::ORDER_STATUS_URL,Configuration::get('Paytm_ENVIRONMENT')))){
					$this->displayCurlerror();
				}else{
				Configuration::updateValue("Paytm_MERCHANT_ID", $_POST["merchant_id"]);
				Configuration::updateValue("Paytm_MERCHANT_KEY", $_POST["merchant_key"]);
				Configuration::updateValue("Paytm_ENVIRONMENT", $_POST["paytm_environment"]);
				Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", $_POST["industry_type"]);
				Configuration::updateValue("Paytm_MERCHANT_WEBSITE", $_POST["website"]);
				Configuration::updateValue("Paytm_EMI_SUBVENTION", $_POST["paytm_emisubvention"]);
				Configuration::updateValue("Paytm_BANK_OFFER", $_POST["paytm_bankoffer"]);
				Configuration::updateValue("Paytm_DC_EMI", $_POST["paytm_dcemi"]);
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
											<span>Based on the selected Environment Mode, copy the relevant Merchant ID for test or production environment available on <a href="https://dashboard.paytm.com/next/apikeys" target="_blank">Paytm dashboard</a>.</span>
										</div>
									</div>
									<div class="form-group">
										<label class="control-label col-lg-3 required"> '.$this->l("Merchant Key").'</label>
										<div class="col-lg-9">
											<input type="text" name="merchant_key" value="' . $field_value['merchant_key'] . '"  class="" required="required"/>
											<span>Based on the selected Environment Mode, copy the Merchant Key for test or production environment available on <a href="https://dashboard.paytm.com/next/apikeys" target="_blank">Paytm dashboard</a>.</span>
										</div>
									</div>
									<div class="form-group">
										<label class="control-label col-lg-3 required"> '.$this->l("Website").'</label>
										<div class="col-lg-9">
											<input type="text" name="website" value="' . $field_value['website'] . '"  class="" required="required"/>
											<span>Enter "WEBSTAGING" for test/integration environment & "DEFAULT" for production environment.</span>
										</div>
									</div>
									<div class="form-group">
										<label class="control-label col-lg-3 required"> '.$this->l("Industry Type").'</label>
										<div class="col-lg-9">
											<input type="text" name="industry_type" value="' . $field_value['industry_type'] . '"  class="" required="required"/>
											<span>Login to <a href="https://dashboard.paytm.com/next/apikeys" target="_blank">Paytm dashboard</a> & copy paste the industry type available there.</span>
										</div>
									</div>		
									<div class="form-group">
										<label class="control-label col-lg-3 required"> '.$this->l("Environment").'</label>
										<div class="col-lg-9">
										<select name="paytm_environment" class="" required="required" >
										<option '.($field_value['paytm_environment'] != "1"? "selected" : "").' value="0" >Staging</option>
										<option '.($field_value['paytm_environment'] == "1"? "selected" : "").' value="1">Production</option>
										</select>
										<span>Select "Staging" for test/integration environment & "Production" once you move to production environment.</span>
										</div>
									</div>
									<div class="form-group">
										<label class="control-label col-lg-3"> '.$this->l("Enable EMI Subvention").'</label>
										<div class="col-lg-9">
										<select name="paytm_emisubvention" class="">
										<option '.($field_value['paytm_emisubvention'] != "1"? "selected" : "").' value="0" >Disable</option>
										<option '.($field_value['paytm_emisubvention'] == "1"? "selected" : "").' value="1">Enable</option>
										</select>
										<span>Get your EMI Subvention plans configured at Paytm & then Select "Yes" to offer EMI Subvention to your customers.</span>
										</div>
									</div>
									<div class="form-group">
										<label class="control-label col-lg-3"> '.$this->l("Enable Bank Offers").'</label>
										<div class="col-lg-9">
										<select name="paytm_bankoffer" class="">
										<option '.($field_value['paytm_bankoffer'] != "1"? "selected" : "").' value="0" >Disable</option>
										<option '.($field_value['paytm_bankoffer'] == "1"? "selected" : "").' value="1">Enable</option>
										</select>
										<span>Get your Bank Offer plans configured at Paytm & then Select "Yes" to provide Bank Offer to your customers.</span>
										</div>
									</div>
									<div class="form-group">
										<label class="control-label col-lg-3"> '.$this->l("Enable DC EMI").'</label>
										<div class="col-lg-9">
										<select name="paytm_dcemi" class="">
										<option '.($field_value['paytm_dcemi'] != "1"? "selected" : "").' value="0" >Disable</option>
										<option '.($field_value['paytm_dcemi'] == "1"? "selected" : "").' value="1">Enable</option>
										</select>
										<span>Get DC EMI enabled for your MID and then select "Yes" to offer DC EMI to your customer. Customer mobile number is mandatory for DC EMI.</span>
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
	    $field_data['paytm_emisubvention']  = isset($data["paytm_emisubvention"])? $data["paytm_emisubvention"] : Configuration::get("Paytm_EMI_SUBVENTION");
	    $field_data['paytm_bankoffer']  = isset($data["paytm_bankoffer"])? $data["paytm_bankoffer"] : Configuration::get("Paytm_BANK_OFFER");
	    $field_data['paytm_dcemi']  = isset($data["paytm_dcemi"])? $data["paytm_dcemi"] : Configuration::get("Paytm_DC_EMI");
								
		return $field_data;
								
	}

	public function hookdisplayPaymentByBinaries($params)
	{
		if (!$this->active) {
			return;
		}
		$btn = '';
		$btn = '<section class="js-payment-binary js-payment-paytm disabled">';
		$btn .= '<button type="button" id="button-confirm" onclick="initTransaction();" class="btn btn-primary center-block">'. PaytmConstants::PAYTM_BUTTON_CONFIRM .'</button> ';
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
		$newOption->setCallToActionText($this->l(''));
		$newOption->setForm($this->generateForm());
		$newOption->setBinary(true);
		$newOption->setModuleName('paytm');
		$newOption->setLogo('../modules/paytm/paytm_logo.png');
		$newOption->setAdditionalInformation('<p>Pay using Credit/Debit Card, NetBanking, Wallet, Postpaid or UPI</p>');
		return [$newOption];
	}
	// frontend Paytm Form
	private function generateForm(){

		global $smarty, $cart;
		$bill_address   = new Address(intval($cart->id_address_invoice));
		$customer       = new Customer(intval($cart->id_customer));
		if (!Validate::isLoadedObject($bill_address) OR ! Validate::isLoadedObject($customer))
			return $this->l("Paytm error: (invalid address or customer)");
		$amount         = $cart->getOrderTotal(true, Cart::BOTH);
		$checkout_url          = str_replace('MID',Configuration::get("Paytm_MERCHANT_ID"), PaytmHelper::getPaytmURL(PaytmConstants::CHECKOUT_JS_URL, Configuration::get('Paytm_ENVIRONMENT')));
		
		?>
		<div id="paytm-pg-spinner" class="paytm-pg-loader">
        <div class="bounce1"></div>
        <div class="bounce2"></div>
        <div class="bounce3"></div>
        <div class="bounce4"></div>
        <div class="bounce5"></div>
        </div>
        <div class="paytm-overlay paytm-pg-loader"></div>
        <style type="text/css">
        #paytm-pg-spinner {margin: 0% auto 0;width: 70px;text-align: center;z-index: 999999;position: relative;display: none;}

        #paytm-pg-spinner > div {width: 10px;height: 10px;background-color: #012b71;border-radius: 100%;display: inline-block;-webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;animation: sk-bouncedelay 1.4s infinite ease-in-out both;}

        #paytm-pg-spinner .bounce1 {-webkit-animation-delay: -0.64s;animation-delay: -0.64s;}

        #paytm-pg-spinner .bounce2 {-webkit-animation-delay: -0.48s;animation-delay: -0.48s;}
        #paytm-pg-spinner .bounce3 {-webkit-animation-delay: -0.32s;animation-delay: -0.32s;}

       #paytm-pg-spinner .bounce4 {-webkit-animation-delay: -0.16s;animation-delay: -0.16s;}
       #paytm-pg-spinner .bounce4, #paytm-pg-spinner .bounce5{background-color: #48baf5;} 
       @-webkit-keyframes sk-bouncedelay {0%, 80%, 100% { -webkit-transform: scale(0) }40% { -webkit-transform: scale(1.0) }}

       @keyframes sk-bouncedelay { 0%, 80%, 100% { -webkit-transform: scale(0);transform: scale(0); } 40% { 
       -webkit-transform: scale(1.0); transform: scale(1.0);}}
      .paytm-overlay{width: 100%;position: fixed;top: 0px;opacity: .4;height: 100%;background: #000;z-index: 15000000;left: 0;display: none;}

</style>
		<script type="application/javascript" crossorigin="anonymous" src="<?php echo $checkout_url;?>"></script>
		<script type="text/javascript">

			function initTransaction(){
				$(".paytm-overlay").css("display","block");
				$("#paytm-pg-spinner").css("display","block");
                var settings = {
                "url": "<?php echo $this->context->link->getModuleLink('paytm', 'ajax', array('paymode' => 'paytm', 'ajax'=>true)); ?>",
                "method": "POST",
                "data" : {},
                };
                $.ajax(settings).done(function (response) {
                	var result  = JSON.parse(response);
                	if (result['txnToken']) {
                		invokeBlinkCheckoutPopup(result['txnToken'],result['paytmOrderId']);
                	}else{

                     $(".paytm-overlay").css("display","none");
			         $("#paytm-pg-spinner").css("display","none");
				     $("#button-confirm").after('<span style="color:red;padding-left:5px;" id="paytmError"></span>');
				     $('#paytmError').text(result['message']);	
                	}
                   
                });

			}

			function invokeBlinkCheckoutPopup(txnToken='',orderId=''){
			  if(document.getElementById("paytmError")!==null){ 
                  document.getElementById("paytmError").remove(); 
                }
			  if(txnToken){
				var config = {
				"root": "",
				"flow": "DEFAULT",
				"data": {
						"orderId": orderId,
						"token": txnToken,
						"tokenType": "TXN_TOKEN",
						"amount": "<?php echo $amount?>",
				},
				"integration": {
                            "platform": "Prestashop",
                            "version": "<?php echo _PS_VERSION_.'|'.$this->version; ?>"  
                },
				"handler": {
					"notifyMerchant": function(eventName,data){
						if(eventName == 'SESSION_EXPIRED'){
								location.reload(); 
						}
					} 
				}
				};
				if(window.Paytm && window.Paytm.CheckoutJS){
						// initialze configuration using init method 
						window.Paytm.CheckoutJS.init(config).then(function onSuccess() {
						// after successfully update configuration invoke checkoutjs
						  $(".paytm-overlay").css("display","none");
			              $("#paytm-pg-spinner").css("display","none");
						  window.Paytm.CheckoutJS.invoke();
						}).catch(function onError(error){
							//console.log("error => ",error);
						});
				} 
			}else{
				$(".paytm-overlay").css("display","none");
			    $("#paytm-pg-spinner").css("display","none");
				$("#button-confirm").after('<span style="color:red;padding-left:5px;" id="paytmError"></span>');
				$('#paytmError').text('<?php  echo $mesage_txt; ?>');
			}
			}
		</script>  
		<?php
		
	//	return $this->display(__FILE__, 'payment_form.tpl');
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
			       $resParams=PaytmHelper::executecUrl(PaytmHelper::getPaytmURL(PaytmConstants::ORDER_STATUS_URL,Configuration::get('Paytm_ENVIRONMENT')), $reqParams);
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
