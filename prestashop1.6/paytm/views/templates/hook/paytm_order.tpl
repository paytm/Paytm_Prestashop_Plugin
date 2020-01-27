<div id="paytmdata" class="panel">
        <div class="panel-heading">
          <i class="icon-money"></i>
          {l s="Paytm Payments" d='Admin.Global'}
        </div>
          <div class="table-responsive">
          <div class="" id="paytm_payment_area">
    <div class="message"></div>
</div>
            <table class="table table-striped table-bordered"  id="paytm_payment_table">
             
        <input type="hidden" value="{$paytm_value.paytm_order_id}" id="paytm_order_id"/>
         
        <input type="hidden" value="{$paytm_value.order_data_id}" id="order_data_id"/>
        
               {foreach from=$paytm_value.paytm_response item=paytm_data key=key}
               {assign var=add_class value=''}
                 {if $key == 'STATUS'}
    {assign var=add_class value='paytm_highlight'}
    {if $paytm_data == 'PENDING'}
    {assign var=add_class value='paytm_highlight redColor'}
    {/if}
    {/if}
      <tr>
        <td class="text-left {$add_class}">{$key}</td>
        <td class="text-left {$add_class}">{$paytm_data}
        
        {if $key == 'STATUS' and $paytm_data eq 'PENDING'}
          <a href="javascript:void(0);" id="button-fetch" class="btn btn-success btn-sm">Fetch Status</a>
          <span class="btn btn-success btn-sm" id="loading-fetch" style="display:none;"><i class="fa fa-circle-o-notch fa-spin fa-lg"></i></span>
        {/if}
        </td>
      </tr>
    {/foreach}
            </table>
          </div>
        </div>
<script type="text/javascript">
    $("#button-fetch").click(function () {
      $('#paytm_payment_area div.message').html('');
      var order_data_id=$("#order_data_id").val();
       var paytm_order_id=$("#paytm_order_id").val();
      
        $.ajax({
          url:'../modules/paytm/ajax.php',
          data: {           action: 'savetxnstatus',
                            token: token,          
                            "paytm_order_id": paytm_order_id,
                            "order_data_id": order_data_id
                        },
         method: 'POST', 
          beforeSend: function () {
            $('#button-fetch').hide();
            $('#loading-fetch').show();
          },
          success: function (data) {
             var obj = JSON.parse(data);
            var html = '';
            if (obj.success == true) {
              var txn_status_btn = false;
              $.each(obj.response, function (index, value) {

                var _class = '';
                if(index == 'STATUS'){
                  _class = 'paytm_highlight ';
                  if(value == 'PENDING'){
                  _class += 'redColor ';
                  }
                }

                html += '<tr>';
                html += '<td class="text-left '+ _class +'">' + index + '</td>';
                html += '<td class="text-left '+ _class +'">' + value + '</td>';
                html += '</tr>';
                if(index == 'STATUS' && value == 'PENDING'){
                  var txn_status_btn = true;
                }
              });

              $('#paytm_payment_table').html(html);
              $('#paytm_payment_area div.message').html('<div class="alert alert-success">' + obj.message +'</div>');
              if(txn_status_btn == false){
                $('#button-fetch').remove();
              }
            }else{
              $('#paytm_payment_area div.message').html('<div class="alert alert-danger">' + obj.message +'</div>');
            }
            $('#loading-fetch').hide();
          }
        });
    });
</script>