{if $status == 'Ok'}
	<p class="success">
		{l s='Your order has been completed.' mod='paytm'}
		<br /><br />{$responseMsg}
		<br /><br />{l s='For any questions or for further information, please contact our' mod='paytm'} <a href="{$base_dir}contact-form.php">{l s='customer support' mod='paytm'}</a>.
		<br /><br />If you would like to view your order history please <a href="order-history" title="{l s='History of Orders' mod='paytm'}">Click Here!</a>
	</p>
{else}
	<p class="error">
		{$responseMsg}
		<br /><br /><a href="{$base_dir}contact-form.php">{l s='customer support' mod='paytm'}</a>.
		<br /><br />If you would like to view your order history please <a href="order-history" title="{l s='History of Orders' mod='paytm'}">Click Here!</a>
	<p></p>
{/if}
