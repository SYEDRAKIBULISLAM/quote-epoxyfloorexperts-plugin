<?php
/**
 * Enqueue Epoxy quote assets and pass ZIP service area + config to JS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load valid ZIP codes from plugin CSV (one per line, trimmed).
 *
 * @return string[]
 */
function efex_load_valid_zip_codes() {
	$path = EFEX_PLUGIN_DIR . 'assets/zip_codes.csv';
	if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
		return array();
	}

	$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	if ( ! is_array( $lines ) ) {
		return array();
	}

	return array_map( 'trim', array_map( 'strval', $lines ) );
}

/**
 * Enqueue CSS/JS for the quote form.
 */
function efex_enqueue_quote_form_assets() {
	wp_enqueue_style(
		'efex-quote-form-style',
		EFEX_PLUGIN_URL . 'css/quote-form.css?v=2.1.0',
		array(),
		'1.0.2'
	);

	wp_enqueue_script(
		'efex-quote-form-script',
		EFEX_PLUGIN_URL . 'js/quote-form.js?v=2.1.0',
		array( 'jquery' ),
		'1.0.2',
		true
	);

	$zip_codes = efex_load_valid_zip_codes();

	// If ZIP list is empty, JS + PHP will treat ZIP validation as "not enforced".
	wp_localize_script(
		'efex-quote-form-script',
		'efexQuoteForm',
		array(
			'validZipCodes'   => $zip_codes,
			'submitUrl'       => admin_url( 'admin-post.php?action=efex_epoxy_quote_submit' ),
			'nonce'           => wp_create_nonce( 'efex_epoxy_quote_submit' ),
			'outOfAreaText'  => 'Thank you for your interest! At this time, we do not service your area.',
			'renterText'     => 'We only provide services for property owners.',
			'commercialValue' => 'commercial_owner',
			'zipErrorText'    => __( 'Please enter a valid ZIP code.', 'epoxy-floor-experts-quote' ),
		)
	);
}