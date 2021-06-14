<?php
require_once(dirname(__FILE__).'/../../lib/PaytmHelper.php');
require_once(dirname(__FILE__).'/../../lib/PaytmChecksum.php');
class PaytmAjaxModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();
	
		$res = $this->generate_txn_token();
		echo json_encode($res);
	}

	public function generate_txn_token(){

		$json = array();
		$cart = $this->context->cart;



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




		$paramData = array('amount' => $amount, 'order_id' => $order_id, 'cust_id' => $cust_id, 'email' => $email, 'mobile_no' => $mobile_no);

		$apiURL = PaytmHelper::getPaytmURL(PaytmConstants::INITIATE_TRANSACTION_URL, Configuration::get('Paytm_ENVIRONMENT')) . '?mid='.Configuration::get('Paytm_MERCHANT_ID').'&orderId='.$paramData['order_id'];
		$paytmParams = array();

		$callbackUrl = "";
		if(!empty(PaytmConstants::CUSTOM_CALLBACK_URL)){
			$callbackUrl =  PaytmConstants::CUSTOM_CALLBACK_URL;
		}else{
			$callbackUrl =  $this->context->link->getModuleLink('paytm', 'validation');
		}

		//error_reporting(1);

		$paytmParams["body"] = array(
			"requestType"   => "Payment",
			"mid"           => Configuration::get('Paytm_MERCHANT_ID'),
			"websiteName"   => Configuration::get("Paytm_MERCHANT_WEBSITE"),
			"orderId"       => $paramData['order_id'],
			"callbackUrl"   => $callbackUrl,
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

		$response = PaytmHelper::executecUrl($apiURL, $paytmParams);


		if(!empty($response['body']['txnToken'])){
			$data['txnToken'] = $response['body']['txnToken'];
			$data['message'] = PaytmConstants::TOKEN_GENERATED_SUCCESSFULLY;
			$data['paytmOrderId'] = $order_id;
		}else{
			$data['txnToken'] = '';
			$data['message'] = PaytmConstants::ERROR_SOMETHING_WENT_WRONG;
			$data['paytmOrderId'] = $order_id;
		}
		return $data;
	}
}
