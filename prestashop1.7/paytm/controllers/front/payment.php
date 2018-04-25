<?php

class PaytmPaymentModuleFrontController extends ModuleFrontController {
	public $ssl = true;
	
	public function init() {
		parent::init();
	}
	
	public function initContent() {
		parent::initContent();
		
		global $smarty, $cart;

		$bill_address = new Address(intval($cart->id_address_invoice));
		$customer = new Customer(intval($cart->id_customer));

		if (!Validate::isLoadedObject($bill_address) OR ! Validate::isLoadedObject($customer))
			return $this->l("Paytm error: (invalid address or customer)");


		$order_id = intval($cart->id);

		$order_id = "RHL_" . strtotime("now") . "__" . $order_id; // just for testing

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
			$post_variables["CALLBACK_URL"] = $this->module->getDefaultCallbackUrl();
		else
			$post_variables["CALLBACK_URL"] = Configuration::get("Paytm_CALLBACK_URL");


		$post_variables["CHECKSUMHASH"] = getChecksumFromArray($post_variables, Configuration::get("Paytm_MERCHANT_KEY"));

		$smarty->assign(
						array(
							"paytm_post" => $post_variables,
							"action" => Configuration::get("Paytm_GATEWAY_URL")
							)
					);
		
		// return $this->display(__FILE__, 'paytm.tpl');
		$this->setTemplate('module:paytm/views/templates/front/payment_form.tpl');
	}
}
