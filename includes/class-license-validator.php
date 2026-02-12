<?php
/**
 * Simple license validator (scaffold)
 *
 * - Stores license key + domain hash in options
 * - Provides filters to integrate with LemonSqueezy or Woo REST later
 * - Does NOT block functionality yet — only logs/flags status
 */

if ( ! class_exists( 'TLAT_License_Validator' ) ) {
	class TLAT_License_Validator {
		const OPTION_KEY = 'tlat_license_key';
		const OPTION_STATUS = 'tlat_license_status';

		public static function init() : void {
			add_action( 'admin_init', [ __CLASS__, 'maybe_validate' ] );
			add_action( 'admin_notices', [ __CLASS__, 'render_notice' ] );
		}

		public static function maybe_validate() : void {
			$license = trim( (string) get_option( self::OPTION_KEY, '' ) );
			if ( '' === $license ) {
				update_option( self::OPTION_STATUS, [ 'status' => 'missing', 'checked' => time() ] );
				return;
			}

			$domain = self::current_domain();
			$result = apply_filters( 'tlat_validate_license', [
				'status' => 'unknown',
				'message' => 'No validator connected',
				'valid'   => false,
				'license' => substr( $license, 0, 6 ) . '…',
				'domain'  => $domain,
			], $license, $domain );

			// Soft status only — no blocking
			update_option( self::OPTION_STATUS, array_merge( $result, [ 'checked' => time() ] ) );
		}

		public static function render_notice() : void {
			$status = get_option( self::OPTION_STATUS );
			if ( ! is_array( $status ) ) return;
			if ( 'valid' === ( $status['status'] ?? '' ) ) return; // no notice on valid
			?>
			<div class="notice notice-warning is-dismissible">
				<p><strong>Tutor LMS Advanced Tracking:</strong> License status: <?php echo esc_html( $status['status'] ?? 'unknown' ); ?> — <?php echo esc_html( $status['message'] ?? '' ); ?></p>
			</div>
			<?php
		}

		private static function current_domain() : string {
			$home = home_url();
			$host = wp_parse_url( $home, PHP_URL_HOST );
			return is_string( $host ) ? $host : 'unknown';
		}
	}
}

// Note: bootstrap is intentionally NOT auto-loaded. Hook in from main plugin when ready:
// add_action( 'plugins_loaded', [ 'TLAT_License_Validator', 'init' ] );
