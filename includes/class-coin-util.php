<?php

use BitWasp\Bitcoin\Base58;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Address;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use BitWasp\Buffertools\Buffertools;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;

require_once(dirname(DP_PLUGIN_FILE) . '/vendor/autoload.php');

// unused, see comment below
// class InvalidKeySerializationException extends Exception {}

class CoinUtil {

  private static $VERSION_PREFIXES = [
    'drkv' => '02fe52f8',
    'drkp' => '02fe52cc',
    'DRKV' => '3a8061a0',
    'DRKP' => '3a805837',
    'xpub' => '0488b21e',
    'xprv' => '0488ade4',
    'tpub' => '043587cf',
    'tprv' => '04358394',
  ];

  // needed for identification of different BIP32 key types
  private static $VERSION_BYTES_KEY = array(
    // Dash
    '02fe52f8' => array( 'project' => 'dash', 'type' => 'public', 'network' => 'mainnet'),
    '02fe52cc' => array( 'project' => 'dash', 'type' => 'private', 'network' => 'mainnet'),
    '3a8061a0' => array( 'project' => 'dash', 'type' => 'public', 'network' => 'testnet'),
    '3a805837' => array( 'project' => 'dash', 'type' => 'private', 'network' => 'testnet'),

    // Bitcoin
    '0488b21e' => array( 'project' => 'bitcoin', 'type' => 'public', 'network' => 'mainnet'),
    '0488ade4' => array( 'project' => 'bitcoin', 'type' => 'private', 'network' => 'mainnet'),
    '043587cf' => array( 'project' => 'bitcoin', 'type' => 'public', 'network' => 'testnet'),
    '04358394' => array( 'project' => 'bitcoin', 'type' => 'private', 'network' => 'testnet'),
  );

  public static function decode_xkey_header_bytes( $version ) {
    return self::$VERSION_BYTES_KEY[ $version ];
  }

  public static function deserialize_xkey ( $xkey ) {
    // base58 decode or throw up
    $hex = Base58::decodeCheck($xkey)->getHex();

    $orig_version = substr($hex, 0, 8);
    $depth        = substr($hex, 8, 2);
    $parent_fingerprint = substr($hex, 10, 8);
    $child        = substr($hex, 18, 8);
    $chain_code   = substr($hex, 26, 64);
    $key_data     = substr($hex, 90, 66);

    $project = self::$VERSION_BYTES_KEY[ $orig_version ]['project'];
    $network = self::$VERSION_BYTES_KEY[ $orig_version ]['network'];
    $type    = self::$VERSION_BYTES_KEY[ $orig_version ]['type'];

    // build an object
    $xkeyObj = new stdClass();
    $xkeyObj->version = $orig_version;
    $xkeyObj->depth = $depth;
    $xkeyObj->parent_fingerprint = $parent_fingerprint;
    $xkeyObj->child = $child;
    $xkeyObj->chain_code = $chain_code;
    $xkeyObj->key_data = $key_data;

    $key_type = self::get_key_type_from_key_data( $key_data );

    // Probably won't ever implement this, since version bytes support is all
    // screwy across multiple crypto-space projects....
    //
    // if ( $type !== $key_type ) {
    //     throw new InvalidKeySerializationException("Incorrectly serialized xkey -- header bytes don't match key type byte.");
    // }

    return $xkeyObj;
  }

  public static function reserialize_key( $xkey, $project ) {
      $xkeyObj = self::deserialize_xkey( $xkey );
      $intent = array(
          'project' => strtolower($project),
          'network' => self::guess_network_from_xkey( $xkey ),
      );
      $new_xkey = self::serialize_xkey( $xkeyObj, $intent );
      return $new_xkey;
  }

  private static function get_version_bytes( $info ) {
      $bytes = array_search( (array)$info, self::$VERSION_BYTES_KEY );
      return $bytes;
  }


  public static function guess_network_from_xkey( $xkey ) {
      $xkeyObj = self::deserialize_xkey( $xkey );
      $intent = self::decode_xkey_header_bytes( $xkeyObj->version );
      if ( !$intent || !isset($intent['network']) ) {
          $network = 'mainnet';
      }
      else {
          $network = $intent['network'];
      }
      return $network;
  }

  // serialize using correct bytes for project/network/key type
  public static function serialize_xkey( $xkeyObj, $intent ) {
      $type = self::get_key_type_from_key_data( $xkeyObj->key_data );
      $intent['type'] = $type;
      $version = self::get_version_bytes( $intent );

      $hex = $version . $xkeyObj->depth .
        $xkeyObj->parent_fingerprint . $xkeyObj->child .
        $xkeyObj->chain_code . $xkeyObj->key_data;

      $xkey = Base58::encodeCheck(Buffer::hex($hex));
      return $xkey;
  }


  private static function get_key_type_from_key_data($key_data) {
      $key_type;
      $type_byte = substr($key_data, 0, 2);

      if ( '00' === $type_byte )
          $key_type = 'private';
      elseif ( in_array( $type_byte, array( '02', '03' ) ) )
          $key_type = 'public';
      else
          throw new Exception('Public or private key data does not match version type');

      return $key_type;
  }

  public static function is_valid_public_xkey( $xkey ) {
      $xkeyObj;
      try {
          // if succeeds, then it's a valid(ish) key of some kind
          $xkeyObj = self::deserialize_xkey( $xkey );
      }
      catch (\Exception $e) {
          return false;
      }

      // ensure public key
      $type = self::get_key_type_from_key_data( $xkeyObj->key_data );
      if ( 'private' === $type ) {
          return false;
      }

      return true;
  }

}
