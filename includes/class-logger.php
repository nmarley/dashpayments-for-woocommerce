<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Logger {
  public function __construct($filename, $flags = FILE_APPEND) {
    $this->fn = $filename;
    $this->flags = $flags;
  }

  public function debug($msg) {
    $now = date("Y-m-d H:i:s");
    $log_message = $now . ": " . $msg . "\n";
    file_put_contents($this->fn, $log_message, $this->flags);
  }
}

