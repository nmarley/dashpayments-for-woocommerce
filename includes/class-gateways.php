<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class DP_Gateways {

    public static $gateways = array(
        'Dash' => 'dash',
    );

    public static function gateway_id( $currency ) {
        if ( !isset ( self::$gateways[ $currency ] ) ) {
            throw new Exception("Gateway [$currency] not found.");
        }
        return self::$gateways[ $currency ];
    }

    public static function settings_option_for( $currency ) {
        $option = 'woocommerce_' . self::gateway_id( $currency ) . '_settings';
        return $option;
    }

}

