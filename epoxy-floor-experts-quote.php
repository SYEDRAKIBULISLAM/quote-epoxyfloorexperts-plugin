<?php
/**
 * Plugin Name: Epoxy Floor Experts Quote Form
 * Description: Single multi-step quote form for epoxyfloorexperts.com with ZIP gating and lead capture.
 * Version: 1.0.0
 * Author: Epoxy Floor Experts
 * Text Domain: epoxy-floor-experts-quote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EFEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EFEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EFEX_TEXT_DOMAIN', 'epoxy-floor-experts-quote' );

/**
 * Lead Conduit configuration (override in wp-config.php, or via filters in code).
 * - If EFEX_LEADCONDUIT_WEBHOOK_URL is empty, leads will be stored but not POSTed externally.
 */
if ( ! defined( 'EFEX_LEADCONDUIT_WEBHOOK_URL' ) ) {
	// Default LeadConduit Flow Source submit URL (override via WP Admin page or by defining EFEX_LEADCONDUIT_WEBHOOK_URL).
	define( 'EFEX_LEADCONDUIT_WEBHOOK_URL', 'https://are.opta.io/ca/submit' );
}
if ( ! defined( 'EFEX_LEADCONDUIT_PRODUCT_LABEL' ) ) {
	define( 'EFEX_LEADCONDUIT_PRODUCT_LABEL', 'Epoxy Floor Experts' );
}

require_once EFEX_PLUGIN_DIR . 'includes/enqueue.php';
require_once EFEX_PLUGIN_DIR . 'includes/leads.php';
require_once EFEX_PLUGIN_DIR . 'includes/form-handler.php';

register_activation_hook(
	__FILE__,
	function() {
		$table = efex_leads_table_name();
		efex_maybe_create_leads_table( $table );
		update_option( 'efex_db_version', '1.0.0' );
	}
);

/**
 * Shortcode: [efex_epoxy_quote_form]
 */
function efex_epoxy_quote_shortcode() {
	efex_enqueue_quote_form_assets();
	return efex_epoxy_quote_markup();
}
add_shortcode( 'efex_epoxy_quote_form', 'efex_epoxy_quote_shortcode' );

function efex_epoxy_quote_markup() {
	ob_start();

	$action_url = admin_url( 'admin-post.php?action=efex_epoxy_quote_submit' );
	$img_url    = EFEX_PLUGIN_URL . 'images/';
	?>
	<div class="arc-calculator arc-roof-form">
		<div class="arc-calculator-inner">
			<form class="arc-roof-form-form arc-form" action="<?php echo esc_url( $action_url ); ?>" method="post" novalidate>
				<?php wp_nonce_field( 'efex_epoxy_quote_submit', 'efex_epoxy_quote_nonce' ); ?>

				<input type="hidden" name="efex_endflow" class="efex-endflow-flag" value="">
				<input type="hidden" name="efex_endflow_reason" class="efex-endflow-reason" value="">

				<div class="arc-step-progress" hidden>
					<ol class="arc-step-progress-list" role="list" aria-label="<?php echo esc_attr__( 'Form progress', EFEX_TEXT_DOMAIN ); ?>"></ol>
				</div>

				<!-- Step 1: ZIP -->
				<div class="arc-step arc-step-1 active" data-step="1">
					<div class="arc-step1-header">Get your <span>Free Estimate Today</span><?php //echo esc_html__( 'Get your <span>Free Estimate Today</span>', EFEX_TEXT_DOMAIN ); ?></div>
					<div class="arc-step-icon-wrap">
						<img src="<?php echo esc_url( $img_url . 'search.png' ); ?>" alt="" class="arc-step-icon" aria-hidden="true">
					</div>
					<div class="zip-heading">
						<h4>What is your ZIP Code?</h4>
					</div>
				
					<div class="arc-input-wrap arc-zip-wrap">
						<div class="arc-zip-input-group">
							<div class="arc-zip-addon">
								<img src="<?php echo esc_url( $img_url . 'location.png' ); ?>" alt="" class="arc-zip-pin" aria-hidden="true">
							</div>
							<input
								type="text"
								class="arc-input arc-zip"
								name="zip_code"
								placeholder="<?php echo esc_attr__( 'ZIP Code?', EFEX_TEXT_DOMAIN ); ?>"
								maxlength="5"
								pattern="[0-9]*"
								inputmode="numeric"
								autocomplete="postal-code"
							>
						</div>
						<p class="arc-error arc-zip-error" role="alert"><?php echo esc_html__( 'Please enter a valid ZIP code.', EFEX_TEXT_DOMAIN ); ?></p>
					</div>
					<button type="button" class="arc-btn arc-btn-primary arc-btn-get-started"><?php echo esc_html__( 'Get Started', EFEX_TEXT_DOMAIN ); ?></button>
				</div>

				<!-- Step 2: Installation area -->
				<div class="arc-step arc-step-2" data-step="2">
					<div class="arc-step-icon-wrap arc-icon-circle">
						<img src="<?php echo esc_url( $img_url . 'floor.png' ); ?>" alt="" class="arc-step-icon" aria-hidden="true">
					</div>
					<h2 class="arc-step-title"><?php echo esc_html__( 'Where are you looking to have epoxy flooring installed?', EFEX_TEXT_DOMAIN ); ?></h2>
					<p class="arc-step-subtitle"><?php echo esc_html__( "Select the area(s) you'd like to have epoxy flooring installed.", EFEX_TEXT_DOMAIN ); ?></p>
					<div class="arc-options arc-options-checkboxes">
						<label class="arc-radio-wrap">
							<input type="checkbox" name="installation_area[]" value="Garage">
							<span class="arc-radio-label"><?php echo esc_html__( 'Garage', EFEX_TEXT_DOMAIN ); ?></span>
						</label>
						<label class="arc-radio-wrap">
							<input type="checkbox" name="installation_area[]" value="Pool Deck">
							<span class="arc-radio-label"><?php echo esc_html__( 'Pool Deck', EFEX_TEXT_DOMAIN ); ?></span>
						</label>
						<label class="arc-radio-wrap">
							<input type="checkbox" name="installation_area[]" value="Basement">
							<span class="arc-radio-label"><?php echo esc_html__( 'Basement', EFEX_TEXT_DOMAIN ); ?></span>
						</label>
						<label class="arc-radio-wrap">
							<input type="checkbox" name="installation_area[]" value="Patio">
							<span class="arc-radio-label"><?php echo esc_html__( 'Patio', EFEX_TEXT_DOMAIN ); ?></span>
						</label>
						<label class="arc-radio-wrap">
							<input type="checkbox" name="installation_area[]" value="Driveway">
							<span class="arc-radio-label"><?php echo esc_html__( 'Driveway', EFEX_TEXT_DOMAIN ); ?></span>
						</label>
						<label class="arc-radio-wrap">
							<input type="checkbox" name="installation_area[]" value="Walkway">
							<span class="arc-radio-label"><?php echo esc_html__( 'Walkway', EFEX_TEXT_DOMAIN ); ?></span>
						</label>
						<label class="arc-radio-wrap">
							<input type="checkbox" name="installation_area[]" value="Other">
							<span class="arc-radio-label"><?php echo esc_html__( 'Other', EFEX_TEXT_DOMAIN ); ?></span>
						</label>
					</div>
					<p class="arc-error arc-step2-error" role="alert"><?php echo esc_html__( 'Please select at least one installation area.', EFEX_TEXT_DOMAIN ); ?></p>
				</div>

				<!-- Step 3: Contact -->
				<div class="arc-step arc-step-3" data-step="3">
					<div class="arc-step-icon-wrap arc-icon-circle">
						<img src="<?php echo esc_url( $img_url . 'user.png' ); ?>" alt="" class="arc-step-icon" aria-hidden="true">
					</div>
					<h2 class="arc-step-title"><?php echo esc_html__( 'Almost Done! We Just Need Your Details', EFEX_TEXT_DOMAIN ); ?></h2>
					<p class="arc-step-subtitle"><?php echo esc_html__( "We'll reach out to schedule your free estimate.", EFEX_TEXT_DOMAIN ); ?></p>
					<div class="arc-form-row arc-form-row-half">
						<div class="arc-field">
							<label for="efex-first-name"><?php echo esc_html__( 'First Name', EFEX_TEXT_DOMAIN ); ?></label>
							<input type="text" id="efex-first-name" name="first_name" placeholder="<?php echo esc_attr__( 'First Name', EFEX_TEXT_DOMAIN ); ?>">
						</div>
						<div class="arc-field">
							<label for="efex-last-name"><?php echo esc_html__( 'Last Name', EFEX_TEXT_DOMAIN ); ?></label>
							<input type="text" id="efex-last-name" name="last_name" placeholder="<?php echo esc_attr__( 'Last Name', EFEX_TEXT_DOMAIN ); ?>">
						</div>
					</div>
					<div class="arc-form-row">
						<div class="arc-field">
							<label for="efex-email"><?php echo esc_html__( 'Email', EFEX_TEXT_DOMAIN ); ?></label>
							<input type="email" id="efex-email" name="email" placeholder="<?php echo esc_attr__( 'you@email.com', EFEX_TEXT_DOMAIN ); ?>">
						</div>
					</div>
					<div class="arc-form-row">
						<label for="efex-phone"><?php echo esc_html__( 'Phone', EFEX_TEXT_DOMAIN ); ?></label>
						<div class="arc-field arc-phone-wrap">
							<span class="arc-phone-prefix">+1</span>
							<input type="tel" id="efex-phone" class="arc-phone-input" name="phone" placeholder="<?php echo esc_attr__( '(123) 456-7890', EFEX_TEXT_DOMAIN ); ?>">
						</div>
					</div>
					<div class="arc-consent-wrap">
						<label class="arc-consent-label">
							<input type="checkbox" name="consent" value="1" id="efex-consent">
							<span class="arc-consent-text">
							Yes, I agree to be contacted by call or text (including automated) from Epoxy Floor Experts to schedule my appointment, and I accept the <a href="/terms-of-services" target="_blank">Terms</a> and <a href="/privacy-policy" target="_blank">Privacy Policy</a>. Consent is not a condition of purchase.
							</span>
						</label>
					</div>
					<p class="arc-error arc-contact-error" role="alert"><?php echo esc_html__( 'Please fill all required fields and accept the consent.', EFEX_TEXT_DOMAIN ); ?></p>
				</div>

				<div class="efex-endflow-message" aria-live="polite" style="display:none;">
					<p class="efex-endflow-out-of-area">
						<?php echo esc_html__( 'Unfortunately, we do not serve in your area.', EFEX_TEXT_DOMAIN ); ?>
					</p>
				</div>

				<div class="arc-nav">
					<button type="button" class="arc-btn arc-btn-back"><?php echo esc_html__( '← Back', EFEX_TEXT_DOMAIN ); ?></button>
					<button type="button" class="arc-btn arc-btn-next"><?php echo esc_html__( 'Next →', EFEX_TEXT_DOMAIN ); ?></button>
					<button type="submit" class="arc-btn arc-btn-submit" name="efex_epoxy_quote_submit"><?php echo esc_html__( 'Get My Free Estimate', EFEX_TEXT_DOMAIN ); ?></button>
				</div>
			</form>
		</div>
	</div>
	<?php
	return ob_get_clean();
}