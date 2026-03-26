<?php
/**
 * Epoxy quote form submission handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submit handler for the quote form.
 */
function efex_epoxy_quote_handle_submit() {
	if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || ! isset( $_POST['efex_epoxy_quote_nonce'] ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['efex_epoxy_quote_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'efex_epoxy_quote_submit' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	global $wpdb;
	$table = efex_leads_table_name();
	efex_maybe_create_leads_table( $table );

	$zip       = isset( $_POST['zip_code'] ) ? sanitize_text_field( wp_unslash( $_POST['zip_code'] ) ) : '';
	$situation = isset( $_POST['situation'] ) ? sanitize_text_field( wp_unslash( $_POST['situation'] ) ) : '';

	$endflow_reason = isset( $_POST['efex_endflow_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['efex_endflow_reason'] ) ) : '';

	$installation_areas = array();
	if ( isset( $_POST['installation_area'] ) && is_array( $_POST['installation_area'] ) ) {
		$installation_areas = array_map(
			function( $v ) {
				return sanitize_text_field( wp_unslash( $v ) );
			},
			$_POST['installation_area']
		);
	}

	$installation_area = ! empty( $installation_areas ) ? implode( ', ', $installation_areas ) : null;
	$timeframe          = isset( $_POST['timeframe'] ) ? sanitize_text_field( wp_unslash( $_POST['timeframe'] ) ) : null;

	$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
	$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

	$consented         = ! empty( $_POST['consent'] );
	$tf_cert_url       = isset( $_POST['xxTrustedFormCertUrl'] ) ? esc_url_raw( wp_unslash( $_POST['xxTrustedFormCertUrl'] ) ) : null;
	$tf_cert_url       = $tf_cert_url ? $tf_cert_url : null;

	$service_zip_list = efex_load_valid_zip_codes();
	$zip_in_service    = true;
	if ( ! empty( $service_zip_list ) ) {
		$zip_in_service = in_array( $zip, $service_zip_list, true );
	}

	// Branching rules (server-side):
	// - ZIP outside service area => end flow / disqualify
	// - renter => disqualify
	$disqualify_reason = '';
	if ( ! $zip_in_service ) {
		$disqualify_reason = 'out_of_area';
	} elseif ( 'renter' === $situation ) {
		$disqualify_reason = 'renter';
	}

	$situation_labels = array(
		'homeowner'        => 'I am the homeowner',
		'renter'           => 'I rent this home',
		'rental_owner'     => 'This is a rental property that I own',
		'commercial_owner' => 'This is for a commercial business I own',
	);
	$situation_label = isset( $situation_labels[ $situation ] ) ? $situation_labels[ $situation ] : $situation;

	$notes = "Epoxy Quote\n";
	$notes .= 'ZIP: ' . ( $zip ? $zip : 'N/A' ) . "\n";
	$notes .= 'Situation: ' . ( $situation_label ? $situation_label : 'N/A' ) . "\n";
	$notes .= 'Installation Area: ' . ( $installation_area ? $installation_area : 'N/A' ) . "\n";
	$notes .= 'Timeframe: ' . ( $timeframe ? $timeframe : 'N/A' ) . "\n";

	if ( $disqualify_reason ) {
		$notes .= 'Disqualified Reason: ' . $disqualify_reason . "\n";
		if ( $endflow_reason ) {
			$notes .= 'Endflow Reason (Client): ' . $endflow_reason . "\n";
		}
	}

	$is_crm_allowed = empty( $disqualify_reason );
	$send_to_crm    = 0;
	$crm_success    = false;

	// Validate required fields only when not disqualified.
	$is_commercial = ( 'commercial_owner' === $situation );
	$is_qualified  = empty( $disqualify_reason );

	if ( $is_qualified ) {
		// Required contact + consent.
		if ( ! $first_name || ! $last_name || ! $email || ! $phone || ! $consented ) {
			// Treat as not qualified if missing required contact fields.
			$is_crm_allowed   = false;
			$disqualify_reason = $disqualify_reason ? $disqualify_reason : 'missing_contact';
			$is_qualified     = false;
			$notes            .= "Disqualified Reason: missing_contact\n";
		}

		// For non-commercial leads, Step 3 and Step 4 are required.
		if ( $is_commercial ) {
			// Skip area/timeframe validations.
		} else {
			if ( empty( $installation_area ) || ! $timeframe ) {
				$is_crm_allowed   = false;
				$is_qualified     = false;
				$disqualify_reason = $disqualify_reason ? $disqualify_reason : 'missing_steps';
				$notes            .= "Disqualified Reason: missing_steps\n";
			}
		}
	}

	$formatted_phone = preg_replace( '/[^0-9]/', '', $phone );
	if ( strpos( $formatted_phone, '1' ) === 0 && strlen( $formatted_phone ) === 11 ) {
		$formatted_phone = substr( $formatted_phone, 1 );
	}

	// Send to Lead Conduit only when qualified and webhook configured.
	if ( $is_crm_allowed ) {
		$webhook_url = EFEX_LEADCONDUIT_WEBHOOK_URL;
		if ( function_exists( 'get_option' ) ) {
			$webhook_url = get_option( 'efex_leadconduit_webhook_url', $webhook_url );
		}
		$webhook_url = apply_filters( 'efex_leadconduit_webhook_url', $webhook_url );
		$product      = apply_filters( 'efex_leadconduit_product_label', EFEX_LEADCONDUIT_PRODUCT_LABEL );

		if ( $webhook_url ) {
			$postData = array(
				'first_name'           => $first_name,
				'last_name'            => $last_name,
				'phone_1'              => $formatted_phone,
				'email'                => $email,
				'product'              => $product,
				'comments'              => $notes,
				'postal_code'          => $zip,
				'trustedform_cert_url' => $tf_cert_url,
			);

			$response = wp_remote_post(
				$webhook_url,
				array(
					'body'    => $postData,
					'timeout' => 15,
				)
			);

			if ( ! is_wp_error( $response ) ) {
				$response_code = wp_remote_retrieve_response_code( $response );
				$body          = wp_remote_retrieve_body( $response );
				$decoded       = json_decode( $body, true );

				if ( $response_code >= 200 && $response_code < 300 ) {
					if ( is_array( $decoded ) && isset( $decoded['outcome'] ) && 'failure' === $decoded['outcome'] ) {
						$crm_success = false;
					} else {
						$crm_success = true;
					}
				}
			}
		}

		$send_to_crm = $crm_success ? 1 : 0;
	}

	// Store lead row always.
	$wpdb->insert(
		$table,
		array(
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'phone'        => $phone,
			'email'        => $email,
			'zip'          => $zip,
			'situation'    => $situation,
			'installation_area' => $installation_area,
			'timeframe'   => $timeframe,
			'consented'    => $consented ? 1 : 0,
			'trustedform_cert_url' => $tf_cert_url,
			'send_to_crm'  => $send_to_crm,
			'notes'        => $notes,
		),
		array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
			'%d',
			'%s',
		)
	);

	// Redirect:
	$thank_you_url     = get_option( 'efex_thank_you_url', home_url( '/thank-you/' ) );
	$not_qualified_url = get_option( 'efex_not_qualified_url', home_url( '/not-qualified/' ) );

	if ( $disqualify_reason || ! $is_qualified ) {
		wp_safe_redirect( $not_qualified_url );
		exit;
	}

	wp_safe_redirect( $thank_you_url );
	exit;
}

add_action( 'admin_post_nopriv_efex_epoxy_quote_submit', 'efex_epoxy_quote_handle_submit' );
add_action( 'admin_post_efex_epoxy_quote_submit', 'efex_epoxy_quote_handle_submit' );

