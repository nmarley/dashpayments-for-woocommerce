<?php
/**
 * Invoice paid email
 */

 if ( ! defined( 'ABSPATH' ) ) {
     exit;
 }

 /**
  * @hooked WC_Emails::email_header() Output the email header
  */
 do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

 <p><?php printf( __( 'Payment for order #%d to address %s has been received in the amount of %s %s.<br>Please take any necessary action for fulfillment and mark as complete. If the order consists entirely of virtual merchandise managed by WooCommerce, no further action is necessary.<br>The order is as follows:', 'dashpay-woocommerce' ), $order->id, $invoice->address, $invoice->orderTotal, $invoice->paymentCurrency ); ?></p>

 <?php


 /**
  * @hooked WC_Emails::order_details() Shows the order details table.
  * @since 2.5.0
  */
 do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

 /**
  * @hooked WC_Emails::order_meta() Shows order meta data.
  */
 do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

 /**
  * @hooked WC_Emails::customer_details() Shows customer details
  * @hooked WC_Emails::email_address() Shows email address
  */
 do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

 /**
  * @hooked WC_Emails::email_footer() Output the email footer
  */
 do_action( 'woocommerce_email_footer', $email );
