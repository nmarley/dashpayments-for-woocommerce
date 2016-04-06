<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once(dirname(DP_PLUGIN_FILE) . '/vendor/autoload.php');

use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Address;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;

class DP_Address_Factory {
  private $project;
  private $currency;
  private $bip32_start_index;
  private $xpub_hash;

  public function __construct($xpub, $currency) {
    // initialize w/xpub key
    $this->xpub_key  = $xpub;
    $this->xpub_hash = md5($this->xpub_key);
    $this->currency  = $currency;
    $this->project   = strtolower( $currency );

    // manage last used index of BIP32 wallet
    $this->bip32_start_index = $this->_get_bip32_start_index();
  }

  private function _get_bip32_start_index() {
    $index = get_option($this->last_used_index_key_name());
    if ( false === $index ) {
      $index = 0;
      update_option( $this->last_used_index_key_name(), $index );
    }
    return $index;
  }

  protected function last_used_index_key_name() {
    return 'dashpay_last_' .
      $this->project . '_xpub_' .
      $this->xpub_hash . '_index';
  }

  private function _incr_bip32_index() {
      $this->bip32_start_index += 1;
      update_option( $this->last_used_index_key_name(), $this->bip32_start_index );
      return;
  }

  private function generate_clean_bip32_address() {
    $addr;
    do {
      $addr = self::get_address_at_index($this->project, $this->xpub_key, $this->bip32_start_index);
      $this->_incr_bip32_index();
    } while ( !$this->is_address_clean($addr) );

    $am = array(
      'address' => $addr,
      'source_hash' => $this->xpub_hash,
      'bip32_index' => ($this->bip32_start_index - 1),
    );

    return $am;
  }

  public function get_clean_address() {
    // check for expired payment addresses & use those, starting from the oldest
    // 'oldest' is defined by lower BIP32 index number
    //
    $am = $this->get_oldest_assigned_address_meta();

    // after those are exhausted, start generating from current index, e.g.:
    //   last_used_index_key_name()
    if ( !$am ) {
      $am = $this->generate_clean_bip32_address();
    }

    return $am;
  }


  private function get_oldest_assigned_address_meta() {
    global $wpdb;

    // get a list of assigned addresses - all WC orders that aren't finalized &
    // are managed by this payment gateway
    $q = new WP_Query(
      array(
        'post_type' => 'shop_order',
        'post_status' => 'wc-failed',
        'meta_query' => array(
          array(
            'key'   => '_payment_method',
            'value' => $this->project,
          ),
          array(
            'key'   => '_reuse_this_address',
            'compare' => 'exists',
          ),
          array(
            'key'   => '_address_meta',
            'compare' => 'exists',
          ),
        ),
        'order' => 'asc',
      )
    );

    $orders = array();
    while ( $q->have_posts() ) {
      $q->the_post();
      $order_id = get_the_ID();
      $orders[] = $order_id;
    }

    // $pool is our 'eligibility pool' of once-assigned addresses that are up for
    // re-assignment.
    //
    $pool = array();

    foreach ( $orders as $order_id ) {
      $invoice = new DP_Invoice( $order_id );

      // check the BIP32 key hash for match
      $am = $invoice->addressMeta;
      if ( $am['source_hash'] === $this->xpub_hash ) {

        if ( $this->is_address_clean($invoice->address) ) {
          // Address is clean, adding to eligible address pool
          $pool[ $am['bip32_index'] ] = $order_id;
        }
        else {
          // in this case the address is BAD for re-use and we need to strip
          // the meta and the re-use flag so that other orders don't pick it up.
          //

          // Strip address re-use and meta fields from this order");
          $invoice->remove_reuse_flag();
          $invoice->clear_address_meta();
        }
      }
    }

    // actually the post_id with the lowest BIP32 index...
    $oldest_post_id = false;
    if ( 0 < count($pool) ) {
      $temp_keys = array_keys($pool);
      sort($temp_keys, SORT_NUMERIC);

      // first one is the oldest order id (earliest BIP32 index)
      $lowest_bip32_index = $temp_keys[0];
      $oldest_post_id = $pool[ $lowest_bip32_index ];
    }

    $the_address_meta = false;
    if ( false !== $oldest_post_id ) {
      $the_invoice = new DP_Invoice( $oldest_post_id );
      // 1st, remove 2 postmeta rows:
      //
      //    1) _reuse_this_address
      //    2) _address_meta

      // save it off for return...
      $the_address_meta = $the_invoice->addressMeta;

      // clear these old fields
      $the_invoice->remove_reuse_flag();
      $the_invoice->clear_address_meta();
    }

    // return the oldest assigned address meta
    return $the_address_meta;
  }


  private function is_address_clean($address) {
    // no transactions ever === clean
    // anything else is not clean

    // Insight network I/O
    $settings_option_name = DP_Gateways::settings_option_for( $this->currency );
    $settings = get_option($settings_option_name);
    $insight_api_url = $settings['insight_api_url'];

    $insight_io = new DP_Insight_API( $insight_api_url );
    $meta = $insight_io->get_address_meta( $address );
    $is_clean = false;

    // transactions
    //
    // Note: the Insight-API was built by Argentine programmers, which named
    // these fields 'apperances' (sic), instead of 'appearances'.
    // So these fields need to stay as written.
    if (
          ( 0 === $meta->unconfirmedTxApperances ) &&
          ( 0 === $meta->txApperances )
    ) {
      // clean
      $is_clean = true;
    }

    return $is_clean;
  }


  private static function get_address_at_index($project, $xpub_key, $key_index) {
    $network_setting = CoinUtil::guess_network_from_xkey( $xpub_key );
    $network_method = ( 'testnet' === $network_setting ) ?
        ($project . 'Testnet') :
        $project ;

    $factory = new NetworkFactory();
    $network = $factory::$network_method();
    Bitcoin::setNetwork( $network );

    // Can enable other derivation paths for other wallets in the future (but
    // Dash-BreadWallet's derivation is apparently broken...)
    $path = "0/" . $key_index;

    $keychain = HierarchicalKeyFactory::fromExtended($xpub_key, $network);
    $key = $keychain->derivePath($path);
    $address = $key->getPublicKey()->getAddress()->getAddress();
    return $address;
  }

}
