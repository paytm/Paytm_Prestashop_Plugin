<?php
class PaytmCurlTestModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();
	
		echo $this->curltest();
	}

	/*
	* Code to test Curl
	*/
	private function curltest(){

		// phpinfo();exit;
		$debug = array();

		if(!function_exists("curl_init")){
			$debug[0]["info"][] = "cURL extension is either not available or disabled. Check phpinfo for more info.";

		// if curl is enable then see if outgoing URLs are blocked or not
		} else {

			// if any specific URL passed to test for
			if(isset($_GET["url"]) && $_GET["url"] != ""){
				$testing_urls = array($_GET["url"]);   
			
			} else {

				// this site homepage URL
				$server = Tools::getHttpHost(true).__PS_BASE_URI__;

				$testing_urls = array(
												$server,
												"www.google.co.in",
												Configuration::get("Paytm_TRANSACTION_STATUS_URL")
											);
			}

			// loop over all URLs, maintain debug log for each response received
			foreach($testing_urls as $key=>$url){

				$debug[$key]["info"][] = "Connecting to <b>" . $url . "</b> using cURL";

				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$res = curl_exec($ch);

				if (!curl_errno($ch)) {
					$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					$debug[$key]["info"][] = "cURL executed succcessfully.";
					$debug[$key]["info"][] = "HTTP Response Code: <b>". $http_code . "</b>";

					$debug_js[] = "URL: " . $url . " | Response Code: " . $http_code;

					// $debug[$key]["content"] = $res;

				} else {
					$debug[$key]["info"][] = "Connection Failed !!";
					$debug[$key]["info"][] = "Error Code: <b>" . curl_errno($ch) . "</b>";
					$debug[$key]["info"][] = "Error: <b>" . curl_error($ch) . "</b>";

					$debug_js[] = "URL: " . $url . " | cURL Error Code: " . curl_errno($ch);

					break;
				}

				curl_close($ch);
			}
		}

		$content = "<center><h1>cURL Test for Paytm Plugin</h1></center><hr/>";
		foreach($debug as $k=>$v){
			$content .= "<ul>";
			foreach($v["info"] as $info){
				$content .= "<li>".$info."</li>";
			}
			$content .= "</ul>";

			// echo "<div style='display:none;'>" . $v["content"] . "</div>";
			$content .= "<hr/>";
		}

		return $content;
	}
	/*
	* Code to test Curl
	*/
}
