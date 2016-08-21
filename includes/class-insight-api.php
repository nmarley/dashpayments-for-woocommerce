<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class DP_Insight_API {

  public $insight_server_url;

  public function __construct( $insight_server_url ) {
    $this->insight_server_url = $insight_server_url;
  }

  public function get_network() {
    $network_status = $this->get_network_status();
    return ( false === $network_status->info->testnet ) ? 'mainnet' : 'testnet';
  }

  private function get_endpoint( $endpoint ) {
    $url = $this->insight_server_url . $endpoint;
    $resp = wp_remote_get( $url , array( 'timeout' => 8 ) );
    if ( is_wp_error( $resp ) ) {
      $message = "Insight API connection failure: " . $resp->get_error_message();
      throw new Exception( $message );
    }

    if ( !$this->is_valid_resp_code($resp) ) {
      throw new Exception("Insight API server error (Invalid HTTP status code).");
    }
    return $resp;
  }

  public function get_network_status() {
    $endpoint = '/api/status';
    $resp = $this->get_endpoint( $endpoint );
    return json_decode( $resp['body'] );
  }

  public function get_sync_status() {
    $endpoint = '/api/sync';
    $resp = $this->get_endpoint( $endpoint );
    return json_decode( $resp['body'] );
  }

  public function is_valid() {
    try {
      $network_status = $this->get_network_status();
      $sync_status = $this->get_sync_status();
    }
    catch ( \Exception $e ) {
      // log message if needed
      return false;
    }

    // verify network status
    if ( "" !== $network_status->info->errors ) {
      // throw new Exception("Insight API status error.");
      return false;
    }

    // verify Insight-API sync status
    if (
          ("finished" !== $sync_status->status)  ||
          (100.0 !== floatval($sync_status->syncPercentage))  ||
          (NULL !== $sync_status->error)
       ) {
      // throw new Exception("Insight API sync error.");
      return false;
    }

    return true;
  }


  public function get_address_meta($address) {
    if ( ! $address ) {
      throw new Exception("Invalid Address");
    }
    $endpoint = '/api/addr/' . $address . '/';
    $resp = $this->get_endpoint( $endpoint );
    return json_decode( $resp['body'] );
  }

  public function get_full_tx( $txid ) {
    if ( ! $txid ) {
      throw new Exception("Invalid TXID");
    }

    $endpoint = '/api/tx/' . $txid;
    $resp = $this->get_endpoint( $endpoint );
    return json_decode($resp['body']);
  }

  private function is_valid_resp_code( $resp ) {
      $http_status_code = wp_remote_retrieve_response_code( $resp );

      // if resp code 2xx, return true
      return ( 1 === preg_match('/^2../', $http_status_code) );
  }

}
