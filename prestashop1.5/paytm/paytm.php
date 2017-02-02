<?php
 
  ini_set('display_errors','On');
	if(!defined('_PS_VERSION_')){
    exit;
	}	
	
	require(dirname(__FILE__) . "/encdec_paytm.php");
	
	class Paytm extends PaymentModule{
    private $_html = '';
    private $_postErrors = array();
 
    function __construct(){
      $this->name = 'paytm';
      $this->tab = 'payments_gateways';
      $this->version = '1.0';
			$this->author = 'PAYTM';
			
 
      parent::__construct(); // The parent construct is required for translations
 
      $this->page = basename(__FILE__, '.php');
      $this->displayName = $this->l('Paytm');
      $this->description = $this->l('Module for accepting payments by Paytm');
 
		}
		
		public function install(){
			if (parent::install()) {
				$sid = $this->getOrderState();
				$fid = $sid+1;				
				
				Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'order_state`( `id_order_state`,`invoice`, `send_email`, `color`, `unremovable`, `logable`, `delivery`) VALUES(' . $sid . ',0, 0, \'#33FF99\', 0, 0,0);');
				Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'order_state_lang`(`id_order_state`, `id_lang`, `name`, `template`) VALUES (' . $sid . ', 1, \'Payment accepted\', \'payment\')');				
				
				Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'order_state`( `id_order_state`,`invoice`, `send_email`, `color`, `unremovable`, `logable`, `delivery`)	VALUES(' . $fid . ',0, 0, \'#33FF99\', 0, 0,0);');
				Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'order_state_lang` (`id_order_state`, `id_lang`, `name`, `template`) VALUES (' . $fid . ', 1, \'Payment Failed\', \'payment\')');
				
				
				Configuration::updateValue('Paytm_MERCHANT_ID', '');
        Configuration::updateValue('Paytm_SECRET_KEY', '');
        Configuration::updateValue('Paytm_GATEWAY_URL', '');
        Configuration::updateValue('Paytm_MERCHANT_INDUSTRY_TYPE', '');
        Configuration::updateValue('Paytm_MERCHANT_CHANNEL_ID', '');
        Configuration::updateValue('Paytm_MERCHANT_WEBSITE', '');        
				Configuration::updateValue('Paytm_ID_ORDER_SUCCESS', $sid);
        Configuration::updateValue('Paytm_ID_ORDER_FAILED', $fid);
				Configuration::updateValue('Paytm_ENABLE_CALLBACK','0');
				Configuration::updateValue('Paytm_ENABLE_MODE','0');
				
				
       	$this->registerHook('payment');	
				
				return true;
			}		
			return false;			
	  }
		
		private function getOrderState() {
        $id = Db::getInstance()->getRow('SELECT max(id_order_state) as id FROM `' . _DB_PREFIX_ . 'order_state`');
        return ++$id['id'];        
    }
		
		
		public function uninstall(){
			Db::getInstance()->Execute('DELETE FROM `' . _DB_PREFIX_ . 'order_state` WHERE id_order_state = ' . Configuration::get('Paytm_ID_ORDER_SUCCESS'));
      Db::getInstance()->Execute('DELETE FROM `' . _DB_PREFIX_ . 'order_state_lang` WHERE id_order_state = ' . Configuration::get('Paytm_ID_ORDER_SUCCESS') . ' and id_lang = 1');
      Db::getInstance()->Execute('DELETE FROM `' . _DB_PREFIX_ . 'order_state` WHERE id_order_state = ' . Configuration::get('Paytm_ID_ORDER_FAILED'));
      Db::getInstance()->Execute('DELETE FROM `' . _DB_PREFIX_ . 'order_state_lang` WHERE id_order_state = ' . Configuration::get('Paytm_ID_ORDER_FAILED') . ' and id_lang = 1');
				
			if (!Configuration::deleteByName('Paytm_MERCHANT_ID') OR !Configuration::deleteByName('Paytm_GATEWAY_URL') OR !Configuration::deleteByName('Paytm_MERCHANT_INDUSTRY_TYPE') OR !Configuration::deleteByName('Paytm_MERCHANT_CHANNEL_ID') OR !Configuration::deleteByName('Paytm_MERCHANT_WEBSITE') OR !Configuration::deleteByName('Paytm_SECRET_KEY') OR !Configuration::deleteByName('Paytm_ID_ORDER_SUCCESS') OR !Configuration::deleteByName('Paytm_ID_ORDER_FAILED') OR !Configuration::deleteByName('Paytm_ENABLE_CALLBACK') OR !Configuration::deleteByName('Paytm_ENABLE_MODE') OR !parent::uninstall()){
        return false;
			}		
      return true;
				
		}
		public function hookPayment($params) {
			
      global $smarty, $cart,$cookie;
			
			
			$bill_address = new Address(intval($params['cart']->id_address_invoice));
      $ship_address = new Address(intval($params['cart']->id_address_delivery));
			$customer = new Customer(intval($params['cart']->id_customer));
			
			if (!Validate::isLoadedObject($bill_address) OR !Validate::isLoadedObject($customer)){
        return $this->l('Paytm error: (invalid address or customer)');
			}	
			
			
			$mobile_no='';
			$email ='';
			try{
				$mobile_no= preg_replace('#[^0-9]{0,13}#is','',$bill_address->phone_mobile);
			}catch(Exception $e){
			
			}
			
			try{
				$email = $customer->email;
			}catch(Exception $e){
			
			}
			
			
			$merchant_id = Configuration::get('Paytm_MERCHANT_ID');
			$mode = (int)Configuration::get('Paytm_ENABLE_MODE');
      $secret_key = Configuration::get('Paytm_SECRET_KEY');
			$industry_type = Configuration::get('Paytm_MERCHANT_INDUSTRY_TYPE');
      $channel_id = Configuration::get('Paytm_MERCHANT_CHANNEL_ID');
      $website = Configuration::get('Paytm_MERCHANT_WEBSITE');
			$callback = (int)Configuration::get('Paytm_ENABLE_CALLBACK');
			
			$amount =  $cart->getOrderTotal(true, Cart::BOTH); 
			
			
      $order_id=(int)($cookie->id_cart); 
			$post_variables = Array(
          "MID" => $merchant_id,
          "ORDER_ID" => $order_id ,
          "CUST_ID" => $params['cart']->id_customer,
          "TXN_AMOUNT" => $amount,
          "CHANNEL_ID" => $channel_id,
          "INDUSTRY_TYPE_ID" => $industry_type,
          "WEBSITE" => $website,
					"MOBILE_NO" =>$mobile_no,
					"EMAIL" => $email
      );
			$callback_html='';
			
			if($callback ==1){
				
				$protocol='http://';
				$host='';
				
				
				if (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
					$protocol='https://';
				}
				
				if (isset($_SERVER["HTTP_HOST"]) && ! empty($_SERVER["HTTP_HOST"])) {
					$host=$_SERVER["HTTP_HOST"];
				}
				$callback_html = "<input type='hidden' name='CALLBACK_URL' value='" . $protocol . $host . __PS_BASE_URI__  . 'modules/paytm/response.php' ."'/>";
				
				$callback_url = $protocol . $host . __PS_BASE_URI__  . 'modules/paytm/response.php';
				$post_variables['CALLBACK_URL']=$callback_url;
				//$post_variables['CALLBACK_URL']=$callback_html;
			}
			$checksum = getChecksumFromArray($post_variables, $secret_key);
			$date= date('Y-m-d H:i:s');
			$smarty->assign(array(
          'merchant_id' => $merchant_id,
          'PaytmUrl' => $this->getPaytmUrl(),
          'date' => $date,
          'amount' => $amount,
          'id_cart' =>$order_id,
          'WEBSITE' => $website,
          'INDUSTRY_TYPE_ID' => $industry_type,
          'CHANNEL_ID' => $channel_id,
					'MOBILE_NO' => $mobile_no,
					'EMAIL' => $email,
          'CUST_ID' => $params['cart']->id_customer,
          'checksum' => $checksum,
					'callback_html' => $callback_html,
          'this_path' => $this->_path
      ));
			
			
			
			return $this->display(__FILE__, 'paytm.tpl');				
		}
		
		
		public function displayFormSettings() {
			
			
			
			$id = Configuration::get('Paytm_MERCHANT_ID');
			$key = Configuration::get('Paytm_SECRET_KEY');
			$url = Configuration::get('Paytm_GATEWAY_URL');
			$itype = Configuration::get('Paytm_MERCHANT_INDUSTRY_TYPE');
			$cid = Configuration::get('Paytm_MERCHANT_CHANNEL_ID');
			$site = Configuration::get('Paytm_MERCHANT_WEBSITE');
			$callback = Configuration::get('Paytm_ENABLE_CALLBACK');
			$mode = Configuration::get('Paytm_ENABLE_MODE');
			

			if (!empty($id)) {
					$merchant_id = $id;
			} else {
					$merchant_id = '';
			}
	
			if (!empty($key)) {
					$secret_key = $key;
			} else {
					$secret_key = '';
			}
			
			if (!empty($url)) {
					$gateway_url = $url;
			} else {
					$gateway_url = '';
			}
			
			if (!empty($itype)) {
					$industry_type = $itype;
			} else {
					$industry_type = '';
			}
			
			if (!empty($cid)) {
					$channel_id = $cid;
			} else {
					$channel_id = '';
			}
			
			if (!empty($site)) {
					$website = $site;
			} else {
					$website = '';
			}
	
			if (!empty($callback) && $callback== 1) {
					$is_callback = 'checked';
			} else {
					$is_callback = '';
			}
			if (!empty($mode) && $mode== 1) {
					$is_mode = 'checked';
			} else {
					$is_mode = '';
			}
	
			$this->_html .= '
				<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
					<fieldset>
						<legend><img src="../img/admin/contact.gif" />' . $this->l('Configuration Settings') . '</legend>
							<table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
								<tr>
									<td colspan="2">' . $this->l('Please specify the Merchant ID and Secret Key provided by Paytm.') . '<br /><br /></td>
								</tr>
								<tr>
									<td width="130" style="height: 25px;">' . $this->l('Paytm Merchant ID') . '</td>
									<td><input type="text" name="merchant_id" value="' . $merchant_id . '" style="width: 170px;" /></td>
								</tr>
								<tr>
									<td width="130" style="height: 25px;">' . $this->l('Paytm Secret Key') . '</td>
									<td><input type="text" name="secret_key" value="' . $secret_key . '" style="width: 170px;" /></td>
								</tr>
								<tr>
									<td width="130" style="height: 25px;">' . $this->l('Paytm Gateway Url') . '</td>
									<td><input type="text" name="gateway_url" value="' . $gateway_url . '" style="width: 170px;" /></td>
								</tr>
								<tr>
									<td width="130" style="height: 25px;">' . $this->l('Paytm Merchant Industry Type') . '</td>
									<td><input type="text" name="industry_type" value="' . $industry_type . '" style="width: 170px;" /></td>
								</tr>
								<tr>
									<td width="130" style="height: 25px;">' . $this->l('Paytm Merchant Channel ID') . '</td>
									<td><input type="text" name="channel_id" value="' . $channel_id . '" style="width: 170px;" /></td>
								</tr>
								<tr>
									<td width="130" style="height: 25px;">' . $this->l('Paytm Merchant Website') . '</td>
									<td><input type="text" name="website" value="' . $website . '" style="width: 170px;" /></td>
								</tr>	
								<tr>
									<td width="130" style="height: 25px;">' . $this->l('Paytm Enable Callback') . '</td>
									<td><input type="checkbox" name="paytm_callback" value="1" style="width: 170px;" ' . $is_callback . '/></td>
								</tr>
								<tr>
									<td width="130" style="height: 25px;">' . $this->l('Paytm Enable Live') . '</td>
									<td><input type="checkbox" name="paytm_mode" value="1" style="width: 170px;" ' . $is_mode . '/></td>
								</tr>
							<tr>
								<td colspan="2" align="center"><br /><input class="button" name="submitPaytm" value="' . $this->l('Update settings') . '" type="submit" /></td>
							</tr>
					</table>
				</fieldset>
			</form>
			';
    }
		
		public function displayConf() {
        $this->_html .= '
					<div class="conf confirm">
						<img src="../img/admin/ok.gif" alt="' . $this->l('Confirmation') . '" />' . $this->l('Settings updated') . 
					'</div>';
    }
		
		
		public function getContent() {
      $this->_html = '<h2>' . $this->displayName . '</h2>';
      if (isset($_POST['submitPaytm'])) {
          if (empty($_POST['merchant_id']))
              $this->_postErrors[] = $this->l('Please Enter your Merchant ID.');
          if (empty($_POST['secret_key']))
              $this->_postErrors[] = $this->l('Please Enter your Secret Key.');
          if (empty($_POST['gateway_url']))
              $this->_postErrors[] = $this->l('Please Enter Paytm Gatewau Url.');
          if (empty($_POST['industry_type']))
              $this->_postErrors[] = $this->l('Please Enter your Industry Type.');
          if (empty($_POST['channel_id']))
              $this->_postErrors[] = $this->l('Please Enter your Merchant Channel ID.');
          if (empty($_POST['website']))
              $this->_postErrors[] = $this->l('Please Enter your Website.');
          
          if (!sizeof($this->_postErrors)) {
							$is_callback=0;
							if(isset($_POST['paytm_callback']) && $_POST['paytm_callback'] == 1){
								$is_callback=1;
							}
							$is_mode=0;
							if(isset($_POST['paytm_mode']) && $_POST['paytm_mode'] == 1){
								$is_mode=1;
							}
              Configuration::updateValue('Paytm_MERCHANT_ID', $_POST['merchant_id']);
              Configuration::updateValue('Paytm_SECRET_KEY', $_POST['secret_key']);
              Configuration::updateValue('Paytm_GATEWAY_URL', $_POST['gateway_url']);
              Configuration::updateValue('Paytm_MERCHANT_INDUSTRY_TYPE', $_POST['industry_type']);
              Configuration::updateValue('Paytm_MERCHANT_CHANNEL_ID', $_POST['channel_id']);
              Configuration::updateValue('Paytm_MERCHANT_WEBSITE', $_POST['website']);
							Configuration::updateValue('Paytm_ENABLE_CALLBACK',$is_callback );
							Configuration::updateValue('Paytm_ENABLE_MODE',$is_mode );
              $this->displayConf();
          } else {
              $this->displayErrors();
          }
      }
      $this->displayPaytm();
      $this->displayFormSettings();
      return $this->_html;
    }
		
		
		public function displayPaytm() {
        $this->_html .= '
					<div style="float: right; width: 440px; height: 150px; border: dashed 1px #666; padding: 8px; margin-left: 12px;">
						<h2>' . $this->l('Open your Paytm Account') . '</h2>
						<div style="clear: both;"></div>
						<br />
						<p>' . $this->l('Click on the Paytm Logo Below to register or edit your Paytm Account') . '</p>
						<p style="text-align: center;"><a href="https://www.Paytm.com/"><img src="../modules/Paytm/logo.gif" alt="Paytm" style="margin-top: 12px;" /></a></p>
						<div style="clear: right;"></div>
				</div>
				<br />
				<b>' . $this->l('This module allows you to accept payments by Paytm.') . 
				'</b><br /><br /><br />' .
				$this->l('If the client chooses this payment mode, your Paytm account will be automatically credited.') .
				'<br /><br />' . 
				$this->l('You need to configure your Paytm account first before using this module.') .
				'<div style="clear:both;">&nbsp;</div>';
    }
		
		
		private function getPaytmUrl() {
      return Configuration::get('Paytm_GATEWAY_URL');
    }
	
		public function processPayment(){
			if (!$this->active){
				return ;
			}
			$order_id= $_POST['ORDERID'];
			global $smarty, $cart, $cookie;
			
			$responseMsg='';
			
			if(isset($_POST['RESPCODE']) && $_POST['RESPCODE'] == "01"){
				$secret_key = Configuration::get('Paytm_SECRET_KEY');
				$merchant_id = Configuration::get('Paytm_MERCHANT_ID');
				$mode = (int)Configuration::get('Paytm_ENABLE_MODE');
				$bool = "FALSE";
				$paramList= $_POST;
				$checksum_recv = $_POST['CHECKSUMHASH'];
				
				$bool = verifychecksum_e($paramList, $secret_key, $checksum_recv);
				$extra_vars['transaction_id'] = $_POST['TXNID'];
				if($bool == "TRUE"){
					// Create an array having all required parameters for status query.
					$requestParamList = array("MID" => $merchant_id , "ORDERID" => $order_id);
					
					// Call the PG's getTxnStatus() function for verifying the transaction status.					
					if($mode=="0")
					{
						$check_status_url = 'https://pguat.paytm.com/oltp/HANDLER_INTERNAL/TXNSTATUS';
					}
					else
					{
						$check_status_url = 'https://secure.paytm.in/oltp/HANDLER_INTERNAL/TXNSTATUS';
					}
					$responseParamList = callAPI($check_status_url, $requestParamList);
					//echo "<pre>"; print_r($responseParamList); die;
					if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$_POST['TXNAMOUNT'])
					{
						$customer = new Customer((int)$cart->id_customer);										
						parent::validateOrder((int) $order_id, Configuration::get('Paytm_ID_ORDER_SUCCESS'), $_POST['TXNAMOUNT'], $this->displayName,null, $extra_vars, null, true, $cart->secure_key, null);
						$result=Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart=' .  $order_id);
						$order = new Order($result['id_order']);
						$order->addOrderPayment($_POST['TXNAMOUNT'], null, $_POST['TXNID']); 
					}
					else{
						parent::validateOrder((int)$order_id,Configuration::get('Paytm_ID_ORDER_FAILED'), $_POST['TXNAMOUNT'],$this->displayName, NULL, $extra_vars,'', false, $cart->secure_key);					
						$result=Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart=' .  $order_id);
						$order = new Order($result['id_order']);
						$order->addOrderPayment($_POST['TXNAMOUNT'], null, $_POST['TXNID']); 
					}
				}else{
					parent::validateOrder((int)$order_id,Configuration::get('Paytm_ID_ORDER_FAILED'), $_POST['TXNAMOUNT'], $this->displayName, NULL, $extra_vars, '', false, $cart->secure_key);					
					$result=Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart=' .  $order_id);
					$order = new Order($result['id_order']);
					$order->addOrderPayment($_POST['TXNAMOUNT'], null, $_POST['TXNID']); 
				}
			}else{ 				
				parent::validateOrder((int)$order_id,Configuration::get('Paytm_ID_ORDER_FAILED'), $_POST['TXNAMOUNT'], $this->displayName, NULL, $extra_vars,'', false, $cart->secure_key);					
				$result=Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart=' .  $order_id);
				$order = new Order($result['id_order']);
			  $order->addOrderPayment($_POST['TXNAMOUNT'], null, $_POST['TXNID']); 
			}
			
			$result=Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart=' .  $order_id);
	
			
		  Tools::redirectLink(__PS_BASE_URI__ . 'order-detail.php?id_order=' . $result['id_order']);
		}
	}	
?>