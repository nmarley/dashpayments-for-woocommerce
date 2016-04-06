/* global woocommerce_admin */

/**
 * WooCommerce Admin JS
 */
jQuery( function ( $ ) {

	// Hidden options
	$( '.hide_options_if_checked' ).each( function() {
		$( this ).find( 'input:eq(0)' ).change( function() {
			if ( $( this ).is( ':checked' ) ) {
				$( this ).closest( 'fieldset, tr' ).nextUntil( '.hide_options_if_checked, .show_options_if_checked', '.hidden_option' ).hide();
			} else {
				$( this ).closest( 'fieldset, tr' ).nextUntil( '.hide_options_if_checked, .show_options_if_checked', '.hidden_option' ).show();
			}
		}).change();
	});


	// Demo store notice
	$( 'input#woocommerce_demo_store' ).change(function() {
		if ( $( this ).is( ':checked' ) ) {
			$( '#woocommerce_demo_store_notice' ).closest( 'tr' ).show();
		} else {
			$( '#woocommerce_demo_store_notice' ).closest( 'tr' ).hide();
		}
	}).change();

});
