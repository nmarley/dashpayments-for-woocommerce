<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class DP_Exchange_Rate {

  private static $exch_rate_api_url = 'http://rates.blackcarrot.be';

  public static function get_exchange_rate($base_currency, $quote_currency) {
    $base_currency  = strtoupper(trim($base_currency));
    $quote_currency = strtoupper(trim($quote_currency));

    if ($base_currency === $quote_currency) {
      return 1.00;
    }

    // ten minutes for caching exchange rate
    $expiration_in_minutes = 10;
    $fx_pair = strtoupper($base_currency . '_' . $quote_currency);

    $transient_key = 'dashpay_exchange_rate_' . $fx_pair;
    $rate = get_transient($transient_key);

    // If false, then the cached value has expired. Pull it again.
    if (false === $rate) {
      $rate = self::fetch_exchange_rate_from_API($fx_pair);
      set_transient($transient_key, $rate, $expiration_in_minutes * 60);
    }

    return $rate;
  }


  protected static function fetch_exchange_rate_from_API( $fxpair ) {
    $endpoint = '/rate/' . $fxpair;
    $url = self::$exch_rate_api_url . $endpoint;

    $resp = wp_remote_get( $url , array( 'timeout' => 8 ) );
    if ( is_wp_error( $resp ) ) {
      throw new Exception("Exchange Rate API connection failure: " . $resp->get_error_message() );
    }

    $body = $resp['body'];
    if ( null === $body || 0 == strlen($body) ) {
      throw new Exception("Exchange Rate API connection failure: " . $obj->error );
    }

    $obj = json_decode($body);
    if ( false === $obj->success ) {
      throw new Exception("Exchange Rate API connection failure: " . $obj->error );
    }

    return $obj->rate;
  }

}

