<?php

class PaytmConstants{
	 CONST TRANSACTION_URL_PRODUCTION			= "https://securegw.paytm.in/order/process";
	 CONST TRANSACTION_STATUS_URL_PRODUCTION	= "https://securegw.paytm.in/order/status";

	 CONST TRANSACTION_URL_STAGING				= "https://securegw-stage.paytm.in/order/process";
	 CONST TRANSACTION_STATUS_URL_STAGING		= "https://securegw-stage.paytm.in/order/status";

	 CONST SAVE_PAYTM_RESPONSE 					= true;
	 CONST CHANNEL_ID							= "WEB";
	 CONST APPEND_TIMESTAMP						= false;
	 CONST ONLY_SUPPORT_INR                     = true;
	 CONST X_REQUEST_ID							= "PLUGIN_PRESTASHOP_";

	 CONST MAX_RETRY_COUNT						= 3;
	 CONST CONNECT_TIMEOUT						= "10";
	 CONST TIMEOUT								= "10";

	 CONST LAST_UPDATED							= "20200120";
	 CONST PLUGIN_VERSION						= "2.0";
	 CONST PLUGIN_DOC_URL						= "https://developer.paytm.com/docs/eCommerce-plugin/prestashop/#v1-6-x";

	 CONST CUSTOM_CALLBACK_URL					= "";

	 CONST PAYTM_PLUGIN_NAME                    = "paytm";
	 CONST PAYTM_TAB                            = "payments_gateways";
	 CONST PAYTM_PLUGIN_AUTHOR                  = "Paytm Development Team";
	 CONST PAYTM_DISPLAYNAME                    = "Paytm";
	 CONST PAYTM_DESCRIPTION                    = "Accept payments by Paytm";
	 CONST PAYTM_PAYMENT_SUCCESS                = "Payment Received";
	 CONST PAYTM_PAYMENT_FAILED                 = "Payment Failed";
	 CONST PAYTM_PAYMENT_PENDING                = "Payment Pending";

	 // Paytm texts
	 CONST TEXT_RESPONSE_ERROR       			= "Something went wrong. Please try again";
	 CONST TEXT_RESPONSE_SUCCESS             	= "Updated <b>STATUS</b> has been fetched";
	 CONST TEXT_RESPONSE_STATUS_SUCCESS			= " and Transaction Status has been updated from <b>PENDING</b> to <b>%s</b>";
	 CONST ERROR_CURL_WARNING           		= "Your server is unable to connect with us. Please contact to Paytm Support.";

	 CONST TEXT_FAILURE       					= "Your payment has been failed!";
	 CONST TEXT_PENDING                         = 'Your payment has been pending!';
	 CONST TEXT_REASON							= "Reason: ";
	 CONST ERROR_SERVER_COMMUNICATION		    = "It seems some issue in server to server communication. Kindly connect with us.";
     CONST ERROR_CHECKSUM_MISMATCH				= "Security Error. Checksum Mismatched!";
     CONST ERROR_AMOUNT_MISMATCH				= "Security Error. Amount Mismatched!";
     CONST ERROR_INVALID_ORDER				    = "No order found to process. Kindly contact with us.";
}

?>
