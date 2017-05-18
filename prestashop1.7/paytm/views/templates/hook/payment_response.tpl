{if $status == 'Ok'}
	<p class="success">
		{l s='Your order has been completed with order id:' mod='paytm'} {$id_order}
		<br /><br />{$responseMsg}
		<br /><br />{l s='For any questions or for further information, please contact our' mod='paytm'} {l s='customer support' mod='paytm'}.
		<br /><br />If you would like to view your order history please <a href="order-history" title="{l s='History of Orders' mod='paytm'}">Click Here!</a>
	</p>
{else}
	<p class="error">
		{l s='It seems that order is not completed successfully.' mod='paytm'} {$responseMsg}
		<br /><br />{l s='For any questions or for further information, please contact our' mod='paytm'} {l s='customer support' mod='paytm'}.
		<br /><br />If you would like to view your order history please <a href="order-history" title="{l s='History of Orders' mod='paytm'}">Click Here!</a>
	<p></p>
{/if}
