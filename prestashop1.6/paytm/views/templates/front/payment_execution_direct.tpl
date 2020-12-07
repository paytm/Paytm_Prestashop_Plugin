{if isset($nbProducts) && $nbProducts <= 0}
    <p class="warning">{l s='Your shopping cart is empty.'}</p>
{else}


	
	<script type="application/javascript" crossorigin="anonymous" src="{$checkout_url}"></script>
			<script type="text/javascript">
				window.addEventListener('load', openBlinkCheckoutPopup, false);
			   function openBlinkCheckoutPopup()
         {
         	// console.log(orderId, txnToken, amount);
         	var config = {
         		"root": "",
         		"flow": "DEFAULT",
         		"data": {
         			"orderId": "{$ORDER_ID}",
					"token": "{$txn_token}",
					"tokenType": "TXN_TOKEN",
					"amount": "{$total}",
         		},
         		"handler": {
         		"notifyMerchant": function(eventName,data){
         			console.log("notifyMerchant handler function called");
         			console.log("eventName => ",eventName);
         			console.log("data => ",data);
         			location.reload();
         		} 
         		}
         	};
         	 if(window.Paytm && window.Paytm.CheckoutJS){
         			// initialze configuration using init method 
         			window.Paytm.CheckoutJS.init(config).then(function onSuccess() {
         				// after successfully updating configuration, invoke checkoutjs
         				window.Paytm.CheckoutJS.invoke();
         			}).catch(function onError(error){
         				console.log("error => ",error);
         			});
         	}
        }
	
			</script>
{/if}
