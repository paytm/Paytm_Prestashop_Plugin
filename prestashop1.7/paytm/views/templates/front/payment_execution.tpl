
<h2>{l s='Order summary' mod='Paytm'}</h2>

{assign var='current_step' value='payment'}

{if isset($nbProducts) && $nbProducts <= 0}
    <p class="warning">{l s='Your shopping cart is empty.'}</p>
{else}

<h3>{l s='You have chosen to pay with Paytm' mod='Paytm'}</h3>
<form name="checkout_confirmation_paytm" id = "checkout_confirmation_paytm" action="{$paytmurl}" method="post" />
	<input type="hidden" name="MID" value="{$merchant_id}" />
	<input type="hidden" name="ORDER_ID" value="{$order_id}" />
	
	<input name="WEBSITE" type="hidden" value="{$website}" />
	<input name="INDUSTRY_TYPE_ID" type="hidden" value="{$industry_type}" />
	<input name="CHANNEL_ID" type="hidden" value="{$channel_id}" />
	<input name="TXN_AMOUNT" type="hidden" value="{$amount}" />
	<input name="EMAIL" type="hidden" value="{$email}" />
	<input name="CUST_ID" type="hidden" value="{$cust_id}" />
	<input name="txnDate" type="hidden" value="{$date}" />
	
	{if $callback_mode == 'ON'}	
		<input name="CALLBACK_URL" type="hidden" value="{$callback_url}" />
	{/if}
	<input name="CHECKSUMHASH" type="hidden" value="{$checksum}" />
    <p>
		{l s='Here is a short summary of your order:' mod='paytm'}
	</p>
	<p style="margin-top:20px;">
		- {l s='The total amount of your order is' mod='paytm'}
			
			
	</p>
	<p>
        {l s='You will be redirected to Paytm to complete your payment.' mod='paytm'}
        <br /><br />
        <b>{l s='Please confirm your order by clicking \'I confirm my order\'' mod='paytm'}.</b>
    </p>
	<p class="cart_navigation">
        
 	</p>
 </form>
<script type="text/javascript">
		
		document.checkout_confirmation_paytm.submit();
		
		</script>
{/if}