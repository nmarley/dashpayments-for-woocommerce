// TODO: ensure we're on the correct page ("thankyou_page")
// -- the one with the QR code & address for payment
//
var intervalId;
var is_finalized;
var instructions_hash;

// store URL params (needed for order_key, to validate order)
var params={};window.location.search.replace(/[?&]+([^=&]+)=([^&]*)/gi,function(str,key,value){params[key] = value;});

function do_the_thing() {

  // TODO: don't insert the order_id as a hidden input if possible

  // TODO: grab the order_key and ensure that a match exists before processing
  // in process_order_callback

  // get the ID & order_key here, then call ajax post...
  var order_id = jQuery('#order_id').val();
  is_finalized = jQuery('#is_finalized').val();

  var order_key = params.key;
  //console.log("params: ...");
  //console.log(params);
  console.log("order_key = [" + order_key + "]");

  console.log("calling ajax url now...");
  jQuery.post(obj.ajaxurl,
      {
          'action': 'check_order',
          'instructions_hash': instructions_hash,
          'order_key': order_key,
          'order_id': order_id
      },
      function(response){
          console.log('The server responded: ' + response);
          var resp = JSON.parse( response );

          // get new instructions hash...
          instructions_hash = resp.instructions_hash;

          // console.log(resp);
          if ( resp.invoice_state_changed ) {
            // console.log("state changed, replacing HTML instructions...");
            var b64_encoded = resp.payment_instructions;
            // console.log("b64_encoded = " + b64_encoded);
            jQuery('#payment_instructions').html( jQuery.base64.decode(b64_encoded) );
          }
          if ( resp.is_order_finalized ) {
            // console.log("invoice in final state, clearing interval!");
            window.clearInterval(intervalId);
          }
      }
  );

}


// initial setup
is_finalized = jQuery('#is_finalized').val();
instructions_hash = '';

if ( !is_finalized ) {
    var intervalId = window.setInterval(do_the_thing, 10000);
}

