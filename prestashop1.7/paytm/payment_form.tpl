<form method="post" id="paytm_form_redirect" action="{$action}" name="checkout_confirmation" class="hidden">
	{foreach from=$paytm_post key=k item=v}
		<input type="hidden" name="{$k}" value="{$v}" />
	{/foreach}
</form>

{if $show_promo_code }
	<div id="promo-code-section">
		<div class="form-fields">
			<div class="form-group row">
				<label class="col-md-3 form-control-label">{l s='Have Promo Code?'}</label>
				<div class="col-lg-6">
					<div class="input-group">
						<input type="text" id="promo_code" name="promo_code" class="form-control" placeholder="{l s='Please enter Promo Code'}">
						<span class="input-group-btn">
							<button id="btn_promo_code" class="btn btn-primary" type="button">{l s='Apply'}</button>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<style>
	.input-group.has-error{
		border: 1px solid #a94442;
	}
	</style>
	<script>
	/*
	* Promo Code functionality starts here
	*/
	var original_checksum = "{$paytm_post['CHECKSUMHASH']}";

	window.onload = function(){
	$("#btn_promo_code").click(function(){

		$("#promo-code-section .has-error").removeClass("has-error");
		$("#promo-code-section .text-danger, #promo-code-section .text-success").remove();

		// if some promo code already applied and now user requests to remove it
		if($(this).hasClass("removePromoCode")){

			// remove promo code from form params
			$("form#paytm_form_redirect input[name=PROMO_CAMP_ID]").remove();
			$("form#paytm_form_redirect input[name=CHECKSUMHASH]").val(original_checksum);


			// enable input to allow user to enter promo code
			$("#promo_code").prop("disabled", false).val("");
			$("#btn_promo_code").addClass("btn-primary").removeClass("btn-danger").removeClass("removePromoCode").text("Apply");

		} else {

			if($("#promo_code").val().trim() == "") {
				$("#promo_code").parent().addClass("has-error");
				return;
			};

			$.ajax({
				url: '{$base_url}index.php?fc=module&module=paytm&controller=ajax',
				type: 'post',
				dataType: 'json',
				data: $("form#paytm_form_redirect").serialize() + "&promo_code="+$("#promo_code").val(),
				success: function(res){
					if(res.success == true){
						// remove old input if there is already exists, to avoid duplicate inputs
						$("form#paytm_form_redirect input[name=PROMO_CAMP_ID]").remove();

						// add promo code to form post
						$("form#paytm_form_redirect").append('<input type="hidden" name="PROMO_CAMP_ID" value="'+$("#promo_code").val()+'"/>');

						// bind new generated checksum
						$("form#paytm_form_redirect input[name=CHECKSUMHASH]").val(res.CHECKSUMHASH);

						$("#promo_code").parent().parent().append("<span class=\"text-success\">"+ res.message +"</span>");

						$("#promo_code").prop("disabled", true);
						$("#btn_promo_code").removeClass("btn-primary").addClass("btn-danger").addClass("removePromoCode").text("Remove");
					} else {
						$("#promo_code").parent().addClass("has-error").parent().append("<span class=\"text-danger\">"+ res.message +"</span>");
					}
				}
			});
		}
	});
}
	/*
	* Promo Code functionality starts here
	*/
	</script>
{else}
	<div>
	  <p>{l s='You will be redirected to Paytm to complete your payment.' mod='paytm'}</p>
	</div>
{/if}
