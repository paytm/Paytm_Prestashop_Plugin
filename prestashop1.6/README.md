# Prestashop 1.6
  
  1. You can download the Prestashop plug-in.
  2. Extract and copy the Prestashop folder in your server.
  3. Place the Paytm kit(paytm.zip file) in Modules folder of the server.
  4. Go to “installed modules” option in “Modules and Services” tab and enable the Paytm payment mode.
  5. Edit and save the below configuration in your Prestashop admin panel
      
      * Merchant ID             - MID provided by Paytm
      * Merchant Key            - Key provided by Paytm
      * Transaction URL         
        * Staging     - https://securegw-stage.paytm.in/theia/processTransaction
        * Production  - https://securegw.paytm.in/theia/processTransaction
      * Transaction Status URL  
        * Staging     - https://securegw-stage.paytm.in/merchant-status/getTxnStatus
        * Production  - https://securegw.paytm.in/merchant-status/getTxnStatus
      * Custom Callback Url     - Disable
      * Callback Url            - customized callback url(this is visible when Custom Callback Url is Enable)
      * Industry Type           - Retail for staging
      		                      Will be provided by Paytm for Production 

  6. Paytm is now installed for your website. You can start accepting payment through Paytm.

# In case of any query, please contact to Paytm.
