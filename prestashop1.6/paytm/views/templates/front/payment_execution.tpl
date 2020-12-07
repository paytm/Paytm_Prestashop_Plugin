{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Order summary' mod='Paytm'}</h2>

{assign var='current_step' value='payment'}

{if isset($nbProducts) && $nbProducts <= 0}
    <p class="warning">{l s='Your shopping cart is empty.'}</p>
{else}

<h3>{l s='You have chosen to pay with Paytm' mod='Paytm'}</h3>


    <p>
		{l s='Here is a short summary of your order:' mod='paytm'}
	</p>
	<p style="margin-top:20px;">
		- {l s='The total amount of your order is' mod='paytm'}
			<span id="amount_{$currency->id}" class="price">{convertPriceWithCurrency price=$total currency=$currency}</span>
			{if $use_taxes == 1}
			{l s='(tax incl.)' mod='paytm'}
			{/if}
	</p>
	<p>
       
        <b>{l s='Please confirm your order by clicking \'I confirm my order\'' mod='paytm'}.</b>
    </p>
	<p class="cart_navigation" id="car_paytm_nav">
       
		<a href="javascript:void(0);" onclick="invokeBlinkCheckoutPopup('{$txn_token}','{$ORDER_ID}','{$total}')" class="exclusive_large">{l s='I confirm my order' mod='checkout'}</a> 
        <a href="{$link->getPageLink('order', true, NULL, "step=3")}" class="button_large">{l s='Other payment methods' mod='paytm'}</a>
 	</p>


{/if}
<script type="application/javascript" crossorigin="anonymous" src="{$checkout_url}"></script>
		<script type="text/javascript">
			function invokeBlinkCheckoutPopup(txnToken, orderId, amount){

				 if(document.getElementById("paytmError")!==null){ 
                  document.getElementById("paytmError").remove(); 
                }
				if(txnToken){
				var config = {
				"root": "",
				"flow": "DEFAULT",
				"data": {
						"orderId": orderId,
						"token": txnToken,
						"tokenType": "TXN_TOKEN",
						"amount": amount,
				},
				"handler": {
					"notifyMerchant": function(eventName,data){
						if(eventName == 'SESSION_EXPIRED'){
							location.reload(); 
						}
					} 
				}
				};
			
				if(window.Paytm && window.Paytm.CheckoutJS){
						// initialze configuration using init method 
						window.Paytm.CheckoutJS.init(config).then(function onSuccess() {
						// after successfully update configuration invoke checkoutjs
						window.Paytm.CheckoutJS.invoke();
						}).catch(function onError(error){
							//console.log("error => ",error);
						});
				} 


				}else{
				jQuery("#car_paytm_nav").append("<div id='paytmError' style='color:red !important;' >{$messsage}</div>");

				}
			}
		</script>  