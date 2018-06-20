<?php
class PaytmAjaxModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();
	
		$res = $this->apply_promo_code();
		echo json_encode($res);
	}

	public function apply_promo_code(){

		$json = array();

		if(isset($_POST["promo_code"]) && trim($_POST["promo_code"]) != "") {

			// if promo code local validation enabled
			if(Configuration::get("Paytm_PROMO_CODE_VALIDATION")){

				$promo_codes = explode(",", Configuration::get("Paytm_PROMO_CODES"));

				$promo_code_found = false;

				foreach($promo_codes as $key=>$val){
					// entered promo code should matched
					if(trim($val) == trim($_POST["promo_code"])) {
						$promo_code_found = true;
						break;
					}
				}

			} else {
				$promo_code_found = true;
			}

			if($promo_code_found){
				$json = array("success" => true, "message" => "Applied Successfully");
				
				$reqParams = $_POST;

				if(isset($reqParams["promo_code"])){
					// PROMO_CAMP_ID is key for Promo Code at Paytm's end
					$reqParams["PROMO_CAMP_ID"] = $reqParams["promo_code"];
				
					// unset promo code sent in request	
					unset($reqParams["promo_code"]);

					// unset CHECKSUMHASH
					unset($reqParams["CHECKSUMHASH"]);
				}

				// create a new checksum with Param Code included and send it to browser
				$json['CHECKSUMHASH'] = getChecksumFromArray($reqParams, Configuration::get("Paytm_MERCHANT_KEY"));
			} else {
				$json = array("success" => false, "message" => "Incorrect Promo Code");
			}
		}

		return $json;
	}
}
