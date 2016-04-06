<?php

function dp_check_unpaid_orders() {
  // for the WP_Query
  global $wpdb;

  $q = new WP_Query(
    array(
      'post_type'   => 'shop_order',
      'post_status' => 'wc-pending',
      'order'       => 'asc',
      'meta_query'  => array(
        array(
          'key'   => '_payment_method',
          'value' => array_values( DP_Gateways::$gateways ),
        )
      ),
    )
  );

  while ( $q->have_posts() ) {
    $q->the_post();
    $order_id = get_the_ID();
    process_order( $order_id );
  }

  return;
}


function process_order( $order_id ) {
  $invoice = new DP_Invoice( $order_id );

  $mutex_key = 'dashpay_processing_' . $order_id;
  $is_processing = get_transient($mutex_key);

  // If false, then not processing this order
  // set the transient & begin processing
  if (false !== $is_processing) {
    return;
  }
  // lock for 90 seconds... plenty of time to complete processing
  set_transient($mutex_key, 1, 90);

  // check any incoming payments
  if ( !$invoice->isFinalized() ) {
    $invoice->checkPayments();
  }

  // expire any orders past expiration time
  // order may have finalized in checkPayments
  if ( !$invoice->isFinalized() ) {
    $invoice->checkExpired();
  }

  // update last checked TS
  $invoice->markLastReviewedAt( time() );

  // finished processing
  delete_transient($mutex_key);

  return;
}


function process_order_callback( ) {
    // filter input from Ajax call & assign vars
    $_POST = filter_input_array( INPUT_POST, FILTER_SANITIZE_STRING );
    $order_id = @$_POST['order_id'];
    $order_key = @$_POST['order_key'];
    $instr_before_hash = @$_POST['instructions_hash'];

    $order_valid = validate_order_id_matches_order_key($order_id, $order_key);
    if ( !$order_valid ) {
        $resp = array(
          'success' => false,
          'error' => 'Order key does not match order id'
        );
        $json_payload = json_encode($resp);
        echo $json_payload;
        wp_die();
    }

    // first time around
    if ( !$instr_before_hash || 0 === strlen($instr_before_hash) ) {
        $invoice = new DP_Invoice( $order_id );
        $instr_before_hash = md5( $invoice->paymentInstructionsHTML() );
    }

    process_order( $order_id );

    // reload DP_Invoice object after changed state
    $invoice = new DP_Invoice( $order_id );

    $instr_after = $invoice->paymentInstructionsHTML();
    $instr_after_hash = md5($instr_after);
    $invoice_state_changed = false;

    // either payment was made or it expired...
    if ( $instr_before_hash !== $instr_after_hash ) {
        $invoice_state_changed = true;
    }

    $resp = array(
      // this just indicates that script tried processing the order
      'success' => true,
      'is_order_finalized'    => $invoice->isFinalized(),
      'invoice_state_changed' => $invoice_state_changed,
      'instructions_hash'     => $instr_after_hash,
    );

    // if state changed, send along the new instructions
    if ( $invoice_state_changed ) {
        $resp['payment_instructions'] = base64_encode($instr_after);
    }

    $json_payload = json_encode($resp);

    echo $json_payload;
    wp_die();
}


function validate_order_id_matches_order_key($order_id, $order_key) {
  global $woocommerce;

  // will return 0 if no matching order found
  $db_order_id = wc_get_order_id_by_order_key( $order_key );

  // do not remove this... in the case an attacker enters '0' as the order_id
  // and any random order_key
  if ( 0 === $db_order_id ) {
    return false;
  }

  return $order_id == $db_order_id;
}
