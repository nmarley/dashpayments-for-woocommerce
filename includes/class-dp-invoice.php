<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// for BaconQrCode
require_once(dirname(DP_PLUGIN_FILE) . '/vendor/autoload.php');

class DP_Invoice {
  public $amountPaid = 0;
  public $orderTotal = 0;
  public $expiresAt;
  public $address;

  const DEFAULT_REQUIRED_CONFIRMATIONS = 6;

  // we want to keep track of txids for future reference
  public $txidList = [];
  public $pendingTxList = [];

  // objectProperty => WP_Order meta map
  private static $op_map = array(
    'paymentCurrency' => '_payment_currency',
    'address'         => 'payment_address',
    'amountPaid'      => 'amount_paid_coins',
    'orderTotal'      => 'order_total_coins',
    'expiresAt'       => 'expires_at',
    'txidList'        => 'txid_list',
    'pendingTxList'   => 'pending_tx_list',
    'addressMeta'     => '_address_meta',
    'lastReviewedAt'  => '_last_reviewed_at',
  );

  private function _loadProperties() {
    // loop thru and load each property value from the WP_Order meta
    foreach ( self::$op_map as $property_name => $meta_key ) {
      $this->{$property_name} = get_post_meta(
        $this->orderId,
        $meta_key,
        true
      );
    }

  }


  private static function _check_init_values($hash) {
    // 1st verify req'd info passed in
    $required_keys = array(
      'order_id',
      'payment_currency',
      'xpub',
      'requested_by_ip',
      'order_total_coins',
      'expires_at',
    );

    $missing = array();
    foreach ( $required_keys as $rkey ) {
      if ( ! isset($hash[$rkey]) ) {
        $missing[] = $rkey;
      }
    }

    if ( 0 < count($missing) ) {
      $ex_msg = "Required argument(s) not supplied: " + join(', ', $missing);
      throw new InvalidArgumentException($ex_msg);
    }

    return;
  }


  public static function create($hash) {
    self::_check_init_values($hash);

    $order_id = $hash['order_id'];

    // then set defaults and assign an address
    $af = new DP_Address_Factory($hash['xpub'], $hash['payment_currency']);

    // guaranteed to be clean
    $clean_addr_info = $af->get_clean_address();

    // set initial values in order meta...
    update_post_meta( $order_id, '_payment_currency', $hash['payment_currency'] );
    update_post_meta( $order_id, 'payment_address', $clean_addr_info['address'] );
    update_post_meta( $order_id, 'requested_by_ip', $hash['requested_by_ip'] );
    update_post_meta( $order_id, 'order_total_coins', $hash['order_total_coins'] );
    update_post_meta( $order_id, 'expires_at', $hash['expires_at'] );
    update_post_meta( $order_id, 'amount_paid_coins', 0 );
    update_post_meta( $order_id, '_last_reviewed_at', 0 );
    update_post_meta( $order_id, 'txid_list', array() );
    update_post_meta( $order_id, 'pending_tx_list', array() );

    // upon paid, remove this meta
    // upon expiry, it will be re-cycled
    update_post_meta( $order_id, '_address_meta', $clean_addr_info );

    return new self( $order_id );
  }


  public function get_qr_payment_code_img_url() {
    $payment_urn = strtolower($this->paymentCurrency) . ':' . $this->address . '?amount=' . $this->remainingBalance();

    $size = 180;

    // set up bacon QR code generator
    $renderer = new \BaconQrCode\Renderer\Image\Png();
    $renderer->setHeight($size);
    $renderer->setWidth($size);
    $renderer->setMargin( 0 );
    $writer = new \BaconQrCode\Writer($renderer);

    $binary = $writer->writeString($payment_urn);
    $b64 = base64_encode( $binary );

    $qr_payment_url = "data:image/png;base64,$b64";

    return $qr_payment_url;
  }


  public function __construct( $order_id ) {
    $this->orderId = $order_id;
    $this->order = new WC_Order( $order_id );

    // load properties from WC_Order meta
    $this->_loadProperties();
  }

  // update last reviewed timestamp... to ensure that we're checking regularly
  public function markLastReviewedAt( $epoch_time ) {
    $this->lastReviewedAt = $epoch_time;
    update_post_meta($this->orderId, '_last_reviewed_at', $this->lastReviewedAt);
  }

  public function paymentInstructionsHTML() {
    $loader = new Twig_Loader_Filesystem(dirname(DP_PLUGIN_FILE) . '/templates');
    $twig = new Twig_Environment($loader);

    if ( $this->isOverPaid() ) {
        $template_file = 'overpaid-instructions.html.twig';
        $template_vars = array(
            'overpaid_amount'  => $this->remainingBalance() * -1,
            'payment_currency' => $this->paymentCurrency,
            'txid_list' => $this->txidList,
            'header_icon' => plugins_url('/assets/images/green-checkmark.png', DP_PLUGIN_FILE),
        );
    }
    elseif ( $this->isPaidInFull() ) {
        $template_file = 'paid-instructions.html.twig';
        $template_vars = array(
            'payment_currency' => $this->paymentCurrency,
            'txid_list' => $this->txidList,
            'header_icon' => plugins_url('/assets/images/green-checkmark.png', DP_PLUGIN_FILE),
        );
    }
    elseif ( $this->isExpired() || 'cancelled' === $this->order->get_status() ) {
        $template_file = 'expired-instructions.html.twig';
        $template_vars = array(
            'header_icon' => plugins_url('/assets/images/red-crossmark.png', DP_PLUGIN_FILE),
        );
    }
    else {
        $settings = $this->gateway_settings();
        $required_confirmations = isset($settings['confirmations']) ?
            $settings['confirmations'] : self::DEFAULT_REQUIRED_CONFIRMATIONS ;

        $template_file = 'payment-instructions.html.twig';
        $template_vars = array(
            'payment_amount' => $this->remainingBalance(),
            'payment_address' => $this->address,
            'qr_code_url' => $this->get_qr_payment_code_img_url(),
            'txid_list' => $this->pendingTxList,
            'required_confirmations' => $required_confirmations,
        );
    }
    $template_vars['order_id'] = $this->orderId;
    $template_vars['is_finalized'] = $this->isFinalized();

    $instructions = $twig->render( $template_file, $template_vars );

    return $instructions;
  }


  private function _saveProperties() {
    foreach ( self::$op_map as $property_name => $meta_key ) {
      update_post_meta($this->orderId, $meta_key, $this->{$property_name});
    }
  }

  public function wasTxAlreadyProcessed( $txid ) {
    return in_array( $txid, $this->txidList );
  }

  public function addPayment($amount_in_coins, $txid) {
    if ( $this->wasTxAlreadyProcessed($txid) )
      return;

    $this->amountPaid = bcadd(
      (string)$this->amountPaid,
      (string)$amount_in_coins,
      8
    );

    // add txid to list
    $this->txidList[] = $txid;

    // remove from pending list
    $this->pendingTxList = array_values(array_diff(
      $this->pendingTxList, array($txid)
    ));

    // persist object properties to order meta
    $this->_saveProperties();

    // change state: either partial or full payment made
    $this->switchStatePostPayment();
  }


  private function switchStatePostPayment() {
    if ($this->isOverPaid()) {
      $this->markAsOverPaid();
    }
    elseif ($this->isPaidInFull()) {
      $this->markAsPaid();
    }
    elseif ($this->isUnderPaid()) {
      $this->markAsUnderPaid();
    }
  }

  public function isExpired() {
    // 10-minute grace period
    $grace = 600;
    return $this->expiresAt < ( time() + $grace );
  }

  private function expire() {
    $this->order->update_status('cancelled', __('Invoice has Expired.', 'dashpay-woocommerce') );

    // if Invoice expired and address has no transactions, re-use the address
    if ( 0 === count($this->txidList) ) {
      update_post_meta($this->orderId, '_reuse_this_address', true);
    }
    else {
      // scrap address meta in order to ensure address not re-used
      $this->clear_address_meta();
    }

    return;
  }

  public function isPaidInFull() {
    return $this->amountPaid >= $this->orderTotal;
  }

  private function markAsPaid() {
    // strip off address re-use flag and address meta
    // -- address has been used and is done
    $this->remove_reuse_flag();
    $this->clear_address_meta();

    $this->order->add_order_note( __('Order Paid in Full', 'dashpay-woocommerce') );

    // a comma-separated list of all TXids used to fund this order
    $tx_record = join(',', $this->txidList);
    $this->order->payment_complete($tx_record);

    // trigger the email...
    do_action( 'dashpayments_invoice_paid_notification', $this->orderId );

    return;
  }

  private function markAsOverPaid() {
    $this->markAsPaid();
    $this->order->add_order_note(
      sprintf(
        esc_html__("Order OverPaid by [%s] Dash", 'dashpay-woocommerce'),
            $this->remainingBalance() * -1
      )
    );
    return;
  }

  private function markAsUnderPaid() {
    // status remains awaiting payment, simply add a note
    $this->order->add_order_note(
      sprintf(
        esc_html__("Order UnderPaid - Balance Remaining = [%s] Dash",
            'dashpay-woocommerce'),
        $this->remainingBalance()
      )
    );
    return;
  }

  public function remainingBalance() {
    return bcsub(
      (string)$this->orderTotal,
      (string)$this->amountPaid,
      8
    );
  }


  public function checkExpired() {
    if ( $this->isFinalized() )
      return;

    if ($this->isExpired()) {
      // TODO: check for incoming (unconfirmed) TX'es and don't expire the
      // order if any exist...
      //
      $this->expire();
    }
  }

  public function checkPayments() {
    if ( $this->isFinalized() )
      return;

    $settings = $this->gateway_settings();
    $insight_api_url = $settings['insight_api_url'];
    $required_confirmations = isset($settings['confirmations']) ?
        $settings['confirmations'] : self::DEFAULT_REQUIRED_CONFIRMATIONS ;

    $insight_io = new DP_Insight_API( $insight_api_url );
    $am = $insight_io->get_address_meta( $this->address );
    $full_tx_list = $am->transactions;

    $unprocessed_tx_list = array_diff( $full_tx_list, $this->txidList );

    foreach ( $unprocessed_tx_list as $txid ) {
      $tx = $insight_io->get_full_tx( $txid );

      // add txid to list of pending txes.
      if ( ! in_array( $txid, $this->pendingTxList ) ) {
        $this->pendingTxList[] = $txid;
        update_post_meta($this->orderId, 'pending_tx_list', $this->pendingTxList);
      }

      // Shim in case user wants to accept zero-confirmations.
      //
      // I really think Insight API should have returned confirmations with 0
      // here instead of leaving it blank. Zero is a valid number of
      // confirmations.
      //
      if ( NULL === @$tx->confirmations ) {
        $tx->confirmations = 0;
      }

      // TODO: IX detection goes here...
      if ( $tx->confirmations >= $required_confirmations ) {
        $amount = $this->aggregate_amount_to_address_from_tx( $tx, $this->address );
        $this->addPayment( $amount, $txid );
      }

    }
  }

  public function isFinalized() {
    $final_states = array( 'completed', 'cancelled', 'refunded', 'failed', 'processing' );
    return in_array( $this->order->get_status(), $final_states );
  }

  public function totalPaid() {
    return $this->amountPaid;
  }

  public function isUnderPaid() {
    $bal = $this->remainingBalance();
    return ( ($bal > 0) && ($bal < $this->orderTotal) );
  }

  public function isOverPaid() {
    return ( 0 > $this->remainingBalance() );
  }

  public function remove_reuse_flag() {
    delete_post_meta($this->orderId, '_reuse_this_address');
  }

  public function clear_address_meta() {
    delete_post_meta($this->orderId, '_address_meta');
  }


  private function aggregate_amount_to_address_from_tx( $tx, $address ) {
    $amount = 0;

    // Loop through all txOUTs and add up the ones with this
    // address (there could be multiple txOUTs to the same address)
    //
    foreach ($tx->vout as $vout) {
      // unused for now, but might need to start tracking vout index later...
      $n          = $vout->n;
      $tx_value   = $vout->value;

      if ( in_array( $address, $vout->scriptPubKey->addresses ) ) {
        $amount = bcadd( (string)$amount, (string)$tx_value, 8 );
      }
    }

    return $amount;
  }

    protected function gateway_settings() {
        $settings_option = DP_Gateways::settings_option_for( $this->paymentCurrency );
        return get_option( $settings_option );
    }

}
