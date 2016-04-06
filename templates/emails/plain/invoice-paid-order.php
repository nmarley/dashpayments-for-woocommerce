<?php
/**
 * Invoice paid email (plain text)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "= " . $email_heading . " =\n\n";

echo sprintf( __( "Payment for order #%d to address %s has been received in the amount of %s %s.\n\nPlease take any necessary action for fulfillment and mark as complete. If the order consists entirely of virtual merchandise managed by WooCommerce, no further action is necessary.\n\nThe order is as follows:", 'dashpay-woocommerce' ), $order->id, $invoice->address, $invoice->orderTotal, $invoice->paymentCurrency ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/**
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
