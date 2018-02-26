<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_CAN_LOAD_FILES_'))
	exit;

require_once(dirname(__FILE__).'/lib/encdec_paytm.php');
	
class Paytm extends PaymentModule
{
	private	$_html = '';
	private $_postErrors = array();
	private $_responseReasonText = null;

	public function __construct(){
		$this->name = 'paytm';
		$this->tab = 'payments_gateways';
		$this->version = '2.5';
		$this->author = 'Paytm Development Team';
		$this->controllers = array('payment', 'response');
        parent::__construct();
		$this->page = basename(__FILE__, '.php');
		
        $this->displayName = $this->l('Paytm');
        $this->description = $this->l('Module for accepting payments by Paytm');
	}
	
	/* public function getPaytmUrl(){
		return Configuration::get('Paytm_GATEWAY_URL');
	} */
	
	public function install(){
		if(parent::install()){
			Configuration::updateValue('PayTM_MERCHANT_ID', '');
            Configuration::updateValue('PayTM_SECRET_KEY', '');
            // Configuration::updateValue('PayTM_MODE', '');
            Configuration::updateValue('transaction_url', '');
            Configuration::updateValue('transaction_status_url', '');
            //Configuration::updateValue('PayTM_GATEWAY_URL', '');
            Configuration::updateValue('PayTM_MERCHANT_INDUSTRY_TYPE', '');
            Configuration::updateValue('PayTM_MERCHANT_CHANNEL_ID', '');
            Configuration::updateValue('PayTM_MERCHANT_WEBSITE', '');
            Configuration::updateValue('PayTM_ENABLE_CALLBACK', '');
           			
			
			//$this->registerHook('payment');
			$this->registerHook('PaymentReturn');
			$this->registerHook('ShoppingCartExtra');
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
	
	public function uninstall(){
		if (!Configuration::deleteByName('PayTM_MERCHANT_ID') OR
			!Configuration::deleteByName('PayTM_SECRET_KEY') OR
			// !Configuration::deleteByName('PayTM_MODE') OR
			!Configuration::deleteByName('transaction_url') OR
			!Configuration::deleteByName('transaction_status_url') OR
			//!Configuration::deleteByName('PayTM_GATEWAY_URL') OR
			!Configuration::deleteByName('PayTM_MERCHANT_INDUSTRY_TYPE') OR
			!Configuration::deleteByName('PayTM_MERCHANT_CHANNEL_ID') OR
			!Configuration::deleteByName('PayTM_MERCHANT_WEBSITE') OR 
			!Configuration::deleteByName('PayTM_ENABLE_CALLBACK') OR			
			!parent::uninstall()){
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
        $this->_html = '<h2>' . $this->displayName . '</h2>';
        if (isset($_POST['submitPayTM'])) {
            if (empty($_POST['merchant_id']))
                $this->_postErrors[] = $this->l('Please Enter your Merchant ID.');
            if (empty($_POST['secret_key']))
                $this->_postErrors[] = $this->l('Please Enter your Secret Key.');
            if (empty($_POST['industry_type']))
                $this->_postErrors[] = $this->l('Please Enter your Industry Type.');
            if (empty($_POST['channel_id']))
                $this->_postErrors[] = $this->l('Please Enter your Merchant Channel ID.');
            if (empty($_POST['website']))
                $this->_postErrors[] = $this->l('Please Enter your Website.');
            /*if (empty($_POST['mode'])){
                $this->_postErrors[] = $this->l('Please Select the Mode, you want to work on .');
            }*/
            if (empty($_POST['transaction_url'])){
                $this->_postErrors[] = $this->l('Please Enter Transaction URL .');
            }
            if (empty($_POST['transaction_status_url'])){
                $this->_postErrors[] = $this->l('Please Enter Status URL .');
            }

            if (!sizeof($this->_postErrors)) {
                Configuration::updateValue('PayTM_MERCHANT_ID', $_POST['merchant_id']);
                Configuration::updateValue('PayTM_SECRET_KEY', $_POST['secret_key']);
                //Configuration::updateValue('PayTM_GATEWAY_URL', $_POST['gateway_url']);
                Configuration::updateValue('PayTM_MERCHANT_INDUSTRY_TYPE', $_POST['industry_type']);
                Configuration::updateValue('PayTM_MERCHANT_CHANNEL_ID', $_POST['channel_id']);
                Configuration::updateValue('PayTM_MERCHANT_WEBSITE', $_POST['website']);
                // Configuration::updateValue('PayTM_MODE', $_POST['mode']);
                Configuration::updateValue('transaction_url', $_POST['transaction_url']);
                Configuration::updateValue('transaction_status_url', $_POST['transaction_status_url']);
                Configuration::updateValue('PayTM_ENABLE_CALLBACK', $_POST['callback']);
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
		$this->_html .= '
		<div class="conf confirm">
			<img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />
			'.$this->l('Settings updated').'
		</div>';
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

        $test = '';
        $live = '';
        $on = '';
        $off = '';
        // $mode = Configuration::get('PayTM_MODE');
        $transaction_url = Configuration::get('transaction_url');
        $transaction_status_url = Configuration::get('transaction_status_url');
        $id = Configuration::get('PayTM_MERCHANT_ID');
        $key = Configuration::get('PayTM_SECRET_KEY');
        //$url = Configuration::get('PayTM_GATEWAY_URL');
        $itype = Configuration::get('PayTM_MERCHANT_INDUSTRY_TYPE');
        $cid = Configuration::get('PayTM_MERCHANT_CHANNEL_ID');
        $site = Configuration::get('PayTM_MERCHANT_WEBSITE');
        $z_callback = Configuration::get('PayTM_ENABLE_CALLBACK');

        if (!empty($transaction_url)) {
            $transaction_url = $transaction_url;
        } else {
            $transaction_url = '';
        }

        if (!empty($transaction_status_url)) {
            $transaction_status_url = $transaction_status_url;
        } else {
            $transaction_status_url = '';
        }

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

        /*if (!empty($mode)) {
            if ($mode == 'TEST') {
                $test = "selected='selected'";
                $live = '';
            }
            if ($mode == 'LIVE') {
                $live = "selected='selected'";
                $test = '';
            }
        } else {
            $live = '';
            $test = '';
        }*/

        if (!empty($z_callback)) {
            if ($z_callback == 'ON') {
                $on = "checked='checked'";
                $off = '';
            }
            if ($z_callback == 'OFF') {
                $off = "checked='checked'";
                $on = '';
            }
        } else {
            $on = '';
            $off = "checked='checked'";
        }

        $this->_html .= '
		<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
			<fieldset>
			<legend><img src="../img/admin/contact.gif" />' . $this->l('Configuration Settings') . '</legend>
				<table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
					<tr><td colspan="2">' . $this->l('Please specify the Merchant ID and Secret Key provided by PayTM.') . '<br /><br /></td></tr>
					<tr>
                                            <td width="130" style="height: 25px;">' . $this->l('PayTM Merchant ID') . '</td>
                                            <td><input type="text" name="merchant_id" value="' . $merchant_id . '" style="width: 170px;" /></td>
                                        </tr>
					<tr>
						<td width="130" style="height: 25px;">' . $this->l('PayTM Merchant Key') . '</td>
						<td><input type="text" name="secret_key" value="' . $secret_key . '" style="width: 170px;" /></td>
					</tr>
                                        
                                        <tr>
                                            <td width="130" style="height: 25px;">' . $this->l('PayTM Merchant Industry Type') . '</td>
                                            <td><input type="text" name="industry_type" value="' . $industry_type . '" style="width: 170px;" /></td>
                                        </tr>
                                        <tr>
                                            <td width="130" style="height: 25px;">' . $this->l('PayTM Merchant Channel ID') . '</td>
                                            <td><input type="text" name="channel_id" value="' . $channel_id . '" style="width: 170px;" /></td>
                                        </tr>
                                        <tr>
                                            <td width="130" style="height: 25px;">' . $this->l('PayTM Merchant Website') . '</td>
                                            <td><input type="text" name="website" value="' . $website . '" style="width: 170px;" /></td>
                                        </tr>
					
					
					<tr>
					    <td width="130" style="height: 25px;">' . $this->l('PayTM Transaction URL') . '</td>
					    <td><input type="text" name="transaction_url" value="' . $transaction_url . '" style="width: 170px;" /></td>
					</tr>
					<tr>
					    <td width="130" style="height: 25px;">' . $this->l('PayTM Transaction Status URL') . '</td>
					    <td><input type="text" name="transaction_status_url" value="' . $transaction_status_url . '" style="width: 170px;" /></td>
					</tr>

					<tr> </tr>
				    <tr>
						<td width="200" style="height: 25px; padding-top:20px;">' . $this->l('Please select PayTM Callback url mode') . '</td>
						<td>
							<input type="radio"  style="width: 110px;" name="callback" value="ON" ' . $on . ' /> On </td><br /><br />
						<td> <input type="radio" name="callback" value="OFF" ' . $off . ' /> Off </td></tr><br /><br />
					<tr><td colspan="2" align="center"><br /><input class="button" name="submitPayTM" value="' . $this->l('Update settings') . '" type="submit" /></td></tr>
				</table>
			</fieldset>
		</form>
		';
    }

		public function hookPaymentOptions($params)
		{

			$newOption = new PaymentOption();
			//$paymentForm = $this->fetch('module:paytm/views/templates/hook/payment.tpl');
			$this->context->smarty->assign(array(
                'path' => $this->_path,
            ));
			
			$newOption->setCallToActionText($this->trans('Pay by Paytm', array(), 'Modules.Paytm.Shop'))
				//->setForm($paymentForm)
				->setLogo(_MODULE_DIR_.'paytm/views/img/logo-mymodule.png')
				//->setAdditionalInformation('your additional Information')
				->setAction($this->context->link->getModuleLink($this->name, 'payment'));
			return [$newOption];
		}
	

		public function execPayment($cart){
		global $smarty,$cart;      
        
		$bill_address = new Address(intval($cart->id_address_invoice));
		$ship_address = new Address(intval($cart->id_address_delivery));
		$bc = new Country($bill_address->id_country);
		$sc = new Country($ship_address->id_country);				
		$customer = new Customer(intval($cart->id_customer));
		
		$account_id= Configuration::get('ACCOUNT_ID');
		$secret_key = Configuration::get('SECRET_KEY');		
		// $mode = Configuration::get('MODE');
		$transaction_url = Configuration::get('transaction_url');
		$transaction_status_url = Configuration::get('transaction_status_url');
		$id_currency = intval(Configuration::get('PS_CURRENCY_DEFAULT'));		
		$currency = new Currency(intval($id_currency));		
		
		$first_name = $bill_address->firstname;
		$last_name = $bill_address->lastname;
		$name = $first_name." ".$last_name;
		$address1 = $bill_address->address1;
		$address2 = $bill_address->address2;
		$address = $address1." ".$address2;		
		$city = $bill_address->city;		
		//echo $country = $bc->iso_code; die;
		$Code = array("AF" =>  "AFG", "AL" => "ALB", "DZ" => "DZA", "AS" => "ASM", "AD" => "AND", "AO" => "AGO", "AI" => "AIA", "AQ" => "ATA", "AG" => "ATG", "AR" => "ARG", "AM" => "ARM","AW" => "ABW", "AU" => "AUS", "AT" => "AUT", "AZ" => "AZE", "BS" => "BHS", "BH" => "BHR","BD" => "BGD", "BB" => "BRB", "BY" => "BLR", "BE" => "BEL", "BZ" => "BLZ", "BJ" => "BEN", "BM" => "BMU", "BT" => "BTN", "BO" => "BOL", "BA" => "BIH", "BW" => "BWA", "BV" => "BVT", "BR" => "BRA", "IO" => "IOT", "VG" => "VGB", "BN" => "BRN", "BG" => "BGR", "BF" => "BFA", "BI" => "BDI","KH" => "KHM", "CM" => "CMR", "CA" => "CAN", "CV" => "CPV", "KY" => "CYM", "CF" => "CAF", "TD" => "TCD", "CL" => "CHL", "CN" => "CHN", "CX" => "CXR", "CC" => "CCK", "CO" => "COL", "KM" => "COM", "CG" => "COG", "CK" => "COK", "CR" => "CRI", "CI" => "CIV", "HR" => "HRV", "CU" => "CUB", "CY" => "CYP", "CZ" => "CZE", "DK" => "DNK", "DM" => "DMA","DO" => "DOM", "TL" => "TLS", "EC" => "ECU", "EG" => "EGY", "SV" => "SLV", "GQ" => "GNQ","ER" => "ERI", "EE" => "EST", "ET" => "ETH", "FK" => "FLK","FO" => "FRO","FJ" => "FJI","FI" => "FIN","FR => FRA","FX" => "FXX","GF" => "GUF","PF" => "PYF","TF" => "ATF","GA" => "GAB","GE" => "GEO","GM" => "GMB","PS" => "PSE","DE" => "DEU","GH" => "GHA","GI" => "GIB","GR" => "GRC","GL" => "GRL","GD" => "GRD","GP" => "GLP","GU" => "GUM","GT" => "GTM","GN" => "GIN","GW" => "GNB","GY" => "GUY","HT" => "HTI","HM" => "HMD","HN" => "HND","HK" => "HKG","HU" => "HUN","IS" => "ISL","IN" => "IND","ID" => "IDN","IQ" => "IRQ","IE" => "IRL","IR" => "IRN","IL" => "ISR","IT" => "ITA","JM" => "JAM","JP" => "JPN","JO" => "JOR","KZ" => "KAZ","KE" => "KEN","KI" => "KIR","KP" => "PRK","KR" => "KOR","KW" => "KWT","KG" => "KGZ","LA" => "LAO","LV" => "LVA","LB" => "LBN","LS" => "LSO","LR" => "LBR","LY" => "LBY","LI" => "LIE","LT"=>"LTU","LU" => "LUX","MO" => "MAC","MK" => "MKD","MG" => "MDG","MW" => "MWI","MY" => "MYS","MV" => "MDV","ML" => "MLI","MT" => "MLT","MH" => "MHL","MQ" => "MTQ","MR" => "MRT","MU" => "MUS","YT" => "MYT","MX" => "MEX","FM" => "FSM","MD" => "MDA","MC" => "MCO","MN" => "MNG","MS" => "MSR","MA" => "MAR","MZ" => "MOZ","MM" => "MMR","NA" => "NAM","NR" => "NRU","NP" => "NPL","NL" => "NLD","NC" => "NCL","NZ" => "NZL","NI" => "NIC","NE" => "NER","NG" => "NGA","NU" => "NIU","NF" => "NFK","MP" => "MNP","NO" => "NOR","OM" => "OMN","PK" => "PAK","PW" => "PLW","PA" => "PAN","PG" => "PNG","PY" => "PRY","PE" => "PER","PH" => "PHL","PN" => "PCN","PL" => "POL","PT" => "PRT","PR" => "PRI","QA" => "QAT","RE" => "REU","RO" => "ROU","RU" => "RUS","RW" => "RWA","LC" => "LCA","WS" => "WSM","SM" => "SMR","ST" => "STP","SA" => "SAU","SN" => "SEN","SC" => "SYC","SL" => "SLE","SG" => "SGP","SK" => "SVK","SI" => "SVN","SB" => "SLB","SO" => "SOM","ZA" => "ZAF","ES" => "ESP","LK" => "LKA","SH" => "SHN","KN" => "KNA","PM" => "SPM","VC" => "VCT","SD" => "SDN","SR"=> "SUR","SJ" => "SJM","SZ" => "SWZ","SE" => "SWE","CH" => "CHE","SY" => "SYR","TW" => "TWN","TJ" => "TJK","TZ" => "TZA","TH" => "THA","TG" => "TGO","TK" => "TKL","TO" => "TON","TT" => "TTO","TN" => "TUN","TR" => "TUR","TM" => "TKM","TC" => "TCA","TV" => "TUV","UG" => "UGA","UA" => "UKR","AE" => "ARE","GB" => "GBR","US" => "USA","VI" => "VIR","UY" => "URY","UZ" => "UZB","VU" => "VUT","VA" => "VAT","VE" => "VEN","VN" => "VNM","WF" => "WLF","EH" => "ESH","YE" => "YEM","CS" => "SCG","ZR" => "ZAR","ZM" => "ZMB","ZW" => "ZWE","AP" => "   ","RS" => "SRB","AX" => "ALA" , "EU" => "" ,"ME" => "MNE","GG" => "GGY","JE" => "JEY","IM" => "IMN","CW" => "CUW","SX" => "SXM"); 
		$country = $Code[$bc->iso_code];
		$state_obj = new State($bill_address->id_state);
		$state = $state_obj->name;
		$phone = $bill_address->phone_mobile;
		$postal_code = $bill_address->postcode;
		$email = $customer->email;			
		$qStrings = array("DR" => "{DR}");
		$return_url = urldecode(Context::getContext()->link->getModuleLink('ebs', 'response', $qStrings, true));
		
		$ship_first_name = $ship_address->firstname;
		$ship_last_name = $ship_address->lastname;
		$ship_name = $ship_first_name." ".$ship_last_name;
		$ship_address1 = $ship_address->address1;
		$ship_address2 = $ship_address->address2;
		$ship_addr = $ship_address1." ".$ship_address2;		
		$ship_city = $ship_address->city;		
		$ship_country = $country;
		$ship_state_obj = new State($ship_address->id_state);
		$ship_state = $state_obj->name;
		$ship_phone = $ship_address->phone_mobile;
		$ship_postal_code = $ship_address->postcode;
		
		
		if (!Validate::isLoadedObject($bill_address) OR !Validate::isLoadedObject($customer))
			return $this->l('Paytm error: (invalid address or customer)');
		
		$amount = $cart->getOrderTotal(true,Cart::BOTH);
		
		
		$protocol='http://';
		$host='';
		if (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
			$protocol='https://';
		}
		if (isset($_SERVER["HTTP_HOST"]) && ! empty($_SERVER["HTTP_HOST"])) {
			$host=$_SERVER["HTTP_HOST"];
		}
		
		
		$ref_no = intval($cart->id);
		//$return_url = 'http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/ebs/response.php?DR={DR}&cart_id='.intval($cart->id);
		$hash = $secret_key ."|". $account_id. "|". $amount . "|".$ref_no."|".html_entity_decode($return_url);
		// $hash = $secret_key ."|". $account_id. "|". $amount . "|".$ref_no."|".html_entity_decode($return_url)."|". $mode;
		$securehash = md5($hash);
		$reference_no = intval($cart->id);
		$description = "Order ID is ".$reference_no;
		$order_id =  uniqid() . $ref_no;
		$date = date('Y-m-d');
		$industry_type = Configuration::get('PayTM_MERCHANT_INDUSTRY_TYPE');
        $channel_id = Configuration::get('PayTM_MERCHANT_CHANNEL_ID');
        $website = Configuration::get('PayTM_MERCHANT_WEBSITE');
		//$paytmurl = Configuration::get('PayTM_GATEWAY_URL');
		$merchant_id = Configuration::get('PayTM_MERCHANT_ID');
        $secret_key = Configuration::get('PayTM_SECRET_KEY');
        $cust_id = intval($cart->id_customer);
		$callback_url = $protocol . $host . __PS_BASE_URI__  . 'index.php?fc=module&module=paytm&controller=response';
		
		
        
		$callback = Configuration::get('PayTM_ENABLE_CALLBACK');
        // $mode = Configuration::get('PayTM_MODE');
        $transaction_url = Configuration::get('transaction_url');
        $mod = $mode;
        /*	19751/17Jan2018	*/
	        /*if ($mod == "TEST"){
	           //$mode = 0;
			   $action_url_paytm = "https://pguat.paytm.com/oltp-web/processTransaction";
			}
	        else{
	           //$mode = 1;
			   $action_url_paytm = "https://secure.paytm.in/oltp-web/processTransaction";
			}*/

	        /*if ($mod == "TEST"){
	           //$mode = 0;
			   $action_url_paytm = "https://securegw-stage.paytm.in/theia/processTransaction";
			} else{
	           //$mode = 1;
			   $action_url_paytm = "https://securegw.paytm.in/theia/processTransaction";
			}*/
			$action_url_paytm = $transaction_url;
		/*	19751/17Jan2018 end	*/
		
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
		if($callback == "ON"){
			$post_variables = Array(
				"MID" => $merchant_id,
				"ORDER_ID" => $order_id,
				"CUST_ID" => $cust_id,
				"TXN_AMOUNT" => $amount,
				"CHANNEL_ID" => $channel_id,
				"INDUSTRY_TYPE_ID" => $industry_type,
				"WEBSITE" => $website,
				"CALLBACK_URL" => $callback_url,
				//"MOBILE_NO" => $mobile_no,
				"EMAIL" => $email
			);
		}
		else{
			$post_variables = Array(
				"MID" => $merchant_id,
				"ORDER_ID" => $order_id,
				"CUST_ID" => $cust_id,
				"TXN_AMOUNT" => $amount,
				"CHANNEL_ID" => $channel_id,
				"INDUSTRY_TYPE_ID" => $industry_type,
				"WEBSITE" => $website,
				//"CALLBACK_URL" => $callback_url,
				//"MOBILE_NO" => $mobile_no,
				"EMAIL" => $email
			);
		}
		$callback_html='';
		
		if(! empty($callback) && stripos($callback,'on') !==false){
			$protocol='http://';
			$host='';
			
			
			if (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
				$protocol='https://';
			}
			
			if (isset($_SERVER["HTTP_HOST"]) && ! empty($_SERVER["HTTP_HOST"])) {
				$host=$_SERVER["HTTP_HOST"];
			}
			//$callback_html = "<input type='hidden' name='CALLBACK_URL' value='" . $protocol . $host . __PS_BASE_URI__  . 'index.php?fc=module&module=paytm&controller=response' ."'/>";
			//$post_variables['CALLBACK_URL']=$protocol . $host . __PS_BASE_URI__  . 'index.php?fc=module&module=paytm&controller=response';
		}
		
        $checksum = getChecksumFromArray($post_variables, $secret_key);
		$smarty->assign(array(
            'merchant_id' =>  $merchant_id,
            'paytmurl' => $action_url_paytm,
            'date' => $date,
		    'order_id' => $order_id,
            'amount' => $amount,
            'website' => $website,
            'industry_type' => $industry_type,
            'channel_id' => $channel_id,
            'cust_id' => $cust_id,
			//'mobile_no' => $mobile_no,
			'email' => $email,
			//'callback_html' => $callback_html,
			'callback_url' => $callback_url,
			'callback_mode' => $callback,
            'checksum' => $checksum
        ));
		//return $this->display(__FILE__, 'payment_execution.tpl');
    }
	public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }		
		
        $state = $params['order']->getCurrentState();
		
		//if($state == '15' || $state == '2'){
			$this->smarty->assign(array(
				'total_to_pay' => Tools::displayPrice(
					$params['order']->getOrdersTotalPaid(),
					new Currency($params['order']->id_currency),
					false
				),
				'shop_name' => $this->context->shop->name,
				//'checkName' => $this->context->checkName,
				//'checkAddress' => Tools::nl2br($this->context->address),
				'status' => 'Ok',
				'responseMsg' => $_GET['responseMsg'],
				'id_order' => $params['order']->id
			));
		//}
		/* else{
			$this->smarty->assign(array(
				'status' => 'failed',
				'responseMsg' => $_GET['responseMsg'],
				));
		} */
        //return $this->fetch('module:paytm/views/templates/hook/payment_response.tpl');
		return $this->display(__FILE__, 'views/templates/hook/payment_response.tpl');
    }
	
}
?>
