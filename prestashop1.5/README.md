# Installation and Configuration

 1. Login to the administrator area of Prestashop.
 2. Go to the modules tab and upload the paytm.zip file
 3. Once it gets installed properly , you can see paytm module in "Payments and Gateways" list. 
 4. Click on the "Payments and Gateways" link, click on configure link for Paytm and enter the merchant information like MerchantID, SercretKey, Gateway URL etc and than click on Update Setting button to save the settings.
 5. Now you can see the Paytm plugin in payment option.
 6. If you have a linux server make sure the Folder permission are set to 755 and file permission to 644.
 7. After you have installed plugin, logout from the administrator area.

# Paytm PG URL Details
	* Staging	
		* Transaction URL             => https://securegw-stage.paytm.in/theia/processTransaction
		* Transaction Status Url      => https://securegw-stage.paytm.in/merchant-status/getTxnStatus

	* Production
		* Transaction URL             => https://securegw.paytm.in/theia/processTransaction
		* Transaction Status Url      => https://securegw.paytm.in/merchant-status/getTxnStatus

See Video : https://www.youtube.com/watch?v=tnVFnt6ljRQ