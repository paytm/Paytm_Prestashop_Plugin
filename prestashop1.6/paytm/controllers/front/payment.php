<?php
class PaytmPaymentModuleFrontController extends ModuleFrontController {
	public $ssl = true;
	
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

		$skip_confirmation = false;
		if($skip_confirmation) {
			$this->display_header = false;
			$this->display_footer = false;
			$this->setTemplate('payment_execution_direct.tpl');
		} else {
			$this->setTemplate('payment_execution.tpl');
		}
	}
}
