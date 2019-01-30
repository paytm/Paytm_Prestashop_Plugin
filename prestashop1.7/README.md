# Prestashop 1.7

1. Download the Prestashop Paytm Plugin.
2. Login to Prestashop admin.
3. Go to Modules from admin menu and click on **UPLOAD A MODULE** button, choose downloaded paytm.zip to upload there. Or you can also extract the downloaded folder (paytm.zip) and paste this folder (paytm) in **Modules** folder under prestashop root folder and then go to **Modules** from admin and click on **Selection** tab, find Paytm module there, click on **INSTALL** button.
4. After finishing installation click on **Configure** button, edit and save the below configuration:
	* Merchant ID - Staging/Production MID provided by Paytm
	* Merchant Key - Staging/Production Key provided by Paytm
	* Website - Provided by Paytm
	* Industry Type - Provided by Paytm
	* Channel ID - WEB/WAP
	* Transaction URL
		* Staging - https://securegw-stage.paytm.in/theia/processTransaction
		* Production - https://securegw.paytm.in/theia/processTransaction
	* Transaction Status URL
		* Staging - https://securegw-stage.paytm.in/merchant-status/getTxnStatus
		* Production - https://securegw.paytm.in/merchant-status/getTxnStatus
	* Custom Callback URL - Disable
	* Callback URL - customized callback URL (this is visible when Custom Callback URL is Enable)
5. Paytm is now installed for your website. You can start accepting payment through Paytm.

See Video : https://www.youtube.com/watch?v=tnVFnt6ljRQ

## In case of any query, please contact to Paytm.