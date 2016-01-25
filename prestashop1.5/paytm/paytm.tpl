<div class="colm-sm-4 paytm">
	<p class="payment_module">
		<a href="javascript:$('#Paytm_form').submit();" title="{l s='Pay with Paytm' mod='Paytm'}">
			<div class="img-tab" style="text-align:center;">
				<img src="{$module_dir}logo.gif" alt="{l s='Pay with Paytm' mod='Paytm'}" style="height:40px;" />
			</div>
			<h4 style="text-align:center;">{l s='CREDIT CARD | DEBIT CARD | NETBANKING' mod='Paytm'}</h4>
		</a>
	</p>	
	<form action="{$PaytmUrl}" method="post" id="Paytm_form" class="hidden">
		<input type="hidden" name="MID" value="{$merchant_id}" />
		<input type="hidden" name="ORDER_ID" value="{$id_cart}" />		
		<input name="WEBSITE" type="hidden" value="{$WEBSITE}" />
		<input name="INDUSTRY_TYPE_ID" type="hidden" value="{$INDUSTRY_TYPE_ID}" />
		<input name="CHANNEL_ID" type="hidden" value="{$CHANNEL_ID}" />
		<input name="TXN_AMOUNT" type="hidden" value="{$amount}" />		
		<input name="CUST_ID" type="hidden" value="{$CUST_ID}" />		
		<input name="MOBILE_NO" type="hidden" value="{$MOBILE_NO}" />		
		<input name="EMAIL" type="hidden" value="{$EMAIL}" />		
		<input name="txnDate" type="hidden" value="{$date}" />
		{$callback_html}
		<input name="CHECKSUMHASH" type="hidden" value="{$checksum}" />		
	</form>
</div>