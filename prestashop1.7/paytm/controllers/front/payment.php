<?php

class PaytmPaymentModuleFrontController extends ModuleFrontController {
	public $ssl = true;
	
	public function init() {
		parent::init();
	}
	
	public function initContent() {
		parent::initContent();
		$cart = $this->context->cart;
		
		$obj = new Paytm();
		$obj->execPayment($cart);


		$this->context->smarty->assign(array(
			'nbProducts' => $cart->nbProducts(),
			'cust_currency' => $cart->id_currency,
			'total' => $cart->getOrderTotal(true, Cart::BOTH),
			'isoCode' => $this->context->language->iso_code,
			'this_path' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

		//$this->setTemplate('payment_execution.tpl');
		$this->setTemplate('module:paytm/views/templates/front/payment_execution.tpl');
		//return $this->fetch('module:paytm/views/templates/front/payment_execution.tpl');
		//return $this->context->smarty->fetch('module:paytm/views/templates/front/payment_execution.tpl');
	}
}
