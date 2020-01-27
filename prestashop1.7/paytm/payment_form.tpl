<form method="post" id="paytm_form_redirect" action="{$action}" name="checkout_confirmation" class="hidden">
	{foreach from=$paytm_post key=k item=v}
		<input type="hidden" name="{$k}" value="{$v}" />
	{/foreach}
</form>


	<div>
	  <p>{l s='You will be redirected to Paytm to complete your payment.' mod='paytm'}</p>
	</div>

