<?php
/**
 * License Validator - communicates with TLAT License Server
 *
 * Handles license activation, validation, and heartbeat via REST API.
 * Stores license key, token, and status in WordPress options.
 * Includes 14-day grace period for expired/invalid licenses.
 *
 * @package TutorLMSAdvancedTracking
 * @since 1.1.0
 */

if ( ! class_exists( 'TLAT_License_Validator' ) ) {
	class TLAT_License_Validator {
		// Option keys
		const OPTION_KEY           = 'tlat_license_key';
		const OPTION_TOKEN         = 'tlat_license_token';
		const OPTION_STATUS        = 'tlat_license_status';
		const OPTION_SERVER_URL    = 'tlat_license_server_url';
		const OPTION_GRACE_START   = 'tlat_license_grace_start';

		// Default license server URL (can be overridden in settings)
		const DEFAULT_SERVER_URL = 'https://license.mahope.dk';

		// Cron hook name
		const CRON_HOOK = 'tlat_license_heartbeat';

		// Cache duration for validation (1 hour)
		const VALIDATION_CACHE_DURATION = HOUR_IN_SECONDS;

		// Grace period duration (14 days in seconds)
		const GRACE_PERIOD_DAYS    = 14;
		const GRACE_PERIOD_SECONDS = 14 * DAY_IN_SECONDS;

		/**
		 * Initialize hooks and cron
		 */
		public static function init(): void {
			add_action( 'admin_init', [ __CLASS__, 'maybe_validate' ] );
			add_action( 'admin_notices', [ __CLASS__, 'render_notice' ] );
			add_action( self::CRON_HOOK, [ __CLASS__, 'send_heartbeat' ] );

			// Schedule heartbeat if not scheduled
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time(), 'daily', self::CRON_HOOK );
			}
		}

		/**
		 * Get the license server URL
		 */
		public static function get_server_url(): string {
			$url = get_option( self::OPTION_SERVER_URL, self::DEFAULT_SERVER_URL );
			return rtrim( $url, '/' );
		}

		/**
		 * Get current domain (normalized)
		 */
		public static function current_domain(): string {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			// Remove www. prefix for consistency
			$host = preg_replace( '/^www\./', '', $host );
			return is_string( $host ) ? strtolower( $host ) : 'unknown';
		}

		/**
		 * Make API request to license server
		 *
		 * @param string $endpoint API endpoint (e.g., '/activate')
		 * @param array  $body     Request body
		 * @param string $method   HTTP method
		 * @return array|WP_Error Response data or error
		 */
		private static function api_request( string $endpoint, array $body = [], string $method = 'POST' ) {
			$url = self::get_server_url() . '/api/v1/license' . $endpoint;

			$args = [
				'method'  => $method,
				'timeout' => 15,
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
			];

			if ( ! empty( $body ) ) {
				$args['body'] = wp_json_encode( $body );
			}

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_array( $body ) ) {
				return new WP_Error( 'invalid_response', 'Invalid response from license server' );
			}

			// Add HTTP status to response for error handling
			$body['_http_status'] = $status_code;

			return $body;
		}

		/**
		 * Activate license for this domain
		 *
		 * @param string $license_key License key to activate
		 * @return array Result with success status and message
		 */
		public static function activate( string $license_key ): array {
			$domain = self::current_domain();

			$response = self::api_request( '/activate', [
				'license_key'    => $license_key,
				'domain'         => $domain,
				'site_url'       => home_url(),
				'wp_version'     => get_bloginfo( 'version' ),
				'plugin_version' => defined( 'TLAT_VERSION' ) ? TLAT_VERSION : '1.0.0',
			] );

			if ( is_wp_error( $response ) ) {
				return [
					'success' => false,
					'status'  => 'error',
					'message' => $response->get_error_message(),
				];
			}

			if ( ! empty( $response['success'] ) ) {
				// Store license key and token
				update_option( self::OPTION_KEY, sanitize_text_field( $license_key ) );
				
				if ( ! empty( $response['token'] ) ) {
					update_option( self::OPTION_TOKEN, sanitize_text_field( $response['token'] ) );
				}

				// Update status
				update_option( self::OPTION_STATUS, [
					'status'    => 'valid',
					'message'   => $response['message'] ?? 'License activated',
					'valid'     => true,
					'checked'   => time(),
					'domain'    => $domain,
					'remaining' => $response['remaining'] ?? null,
				] );

				return [
					'success' => true,
					'status'  => 'valid',
					'message' => $response['message'] ?? 'License activated successfully',
				];
			}

			// Activation failed
			$error_message = $response['message'] ?? 'Activation failed';
			
			update_option( self::OPTION_STATUS, [
				'status'  => $response['error'] ?? 'invalid',
				'message' => $error_message,
				'valid'   => false,
				'checked' => time(),
			] );

			return [
				'success' => false,
				'status'  => $response['error'] ?? 'invalid',
				'message' => $error_message,
			];
		}

		/**
		 * Deactivate license for this domain
		 *
		 * @return array Result with success status and message
		 */
		public static function deactivate(): array {
			$license_key = get_option( self::OPTION_KEY, '' );
			$domain      = self::current_domain();

			if ( empty( $license_key ) ) {
				return [
					'success' => false,
					'message' => 'No license key found',
				];
			}

			$response = self::api_request( '/deactivate', [
				'license_key' => $license_key,
				'domain'      => $domain,
			] );

			// Clear local data regardless of server response
			delete_option( self::OPTION_KEY );
			delete_option( self::OPTION_TOKEN );
			update_option( self::OPTION_STATUS, [
				'status'  => 'deactivated',
				'message' => 'License deactivated',
				'valid'   => false,
				'checked' => time(),
			] );

			if ( is_wp_error( $response ) ) {
				return [
					'success' => true, // Local deactivation succeeded
					'message' => 'Local license removed (server unreachable)',
				];
			}

			return [
				'success' => ! empty( $response['success'] ),
				'message' => $response['message'] ?? 'License deactivated',
			];
		}

		/**
		 * Validate license (called periodically)
		 */
		public static function maybe_validate(): void {
			$license_key = trim( (string) get_option( self::OPTION_KEY, '' ) );

			// No license key stored
			if ( '' === $license_key ) {
				update_option( self::OPTION_STATUS, [
					'status'  => 'missing',
					'message' => 'No license key entered',
					'valid'   => false,
					'checked' => time(),
				] );
				return;
			}

			// Check if we recently validated
			$status = get_option( self::OPTION_STATUS );
			if ( 
				is_array( $status ) && 
				! empty( $status['checked'] ) && 
				( time() - $status['checked'] ) < self::VALIDATION_CACHE_DURATION 
			) {
				return; // Use cached status
			}

			// Perform validation
			self::validate();
		}

		/**
		 * Validate license with server
		 *
		 * @return array Validation result
		 */
		public static function validate(): array {
			$license_key = get_option( self::OPTION_KEY, '' );
			$token       = get_option( self::OPTION_TOKEN, '' );
			$domain      = self::current_domain();

			if ( empty( $license_key ) ) {
				return [ 'valid' => false, 'status' => 'missing' ];
			}

			$body = [
				'license_key' => $license_key,
				'domain'      => $domain,
			];

			// Include token if available for stronger validation
			if ( ! empty( $token ) ) {
				$body['token'] = $token;
			}

			$response = self::api_request( '/validate', $body );

			if ( is_wp_error( $response ) ) {
				// On network error, preserve previous valid status but start grace if needed
				$current = get_option( self::OPTION_STATUS );
				if ( is_array( $current ) && ( $current['valid'] ?? false ) ) {
					$current['message'] = 'Offline validation (server unreachable)';
					$current['checked'] = time();
					update_option( self::OPTION_STATUS, $current );
					return [ 'valid' => true, 'status' => 'offline_grace' ];
				}

				// Start grace period for connection issues
				self::start_grace_period();

				update_option( self::OPTION_STATUS, [
					'status'  => 'error',
					'message' => 'Could not reach license server',
					'valid'   => false,
					'checked' => time(),
				] );

				return [ 'valid' => false, 'status' => 'error' ];
			}

			$is_valid = ! empty( $response['valid'] );

			if ( $is_valid ) {
				// License is valid, end any grace period
				self::end_grace_period();
			} else {
				// License became invalid, start grace period
				self::start_grace_period();
			}

			update_option( self::OPTION_STATUS, [
				'status'  => $is_valid ? 'valid' : ( $response['error'] ?? 'invalid' ),
				'message' => $response['message'] ?? ( $is_valid ? 'License valid' : 'License invalid' ),
				'valid'   => $is_valid,
				'checked' => time(),
				'license' => $response['license'] ?? null,
			] );

			return [
				'valid'  => $is_valid,
				'status' => $is_valid ? 'valid' : ( $response['error'] ?? 'invalid' ),
			];
		}

		/**
		 * Send heartbeat to license server (called via cron)
		 */
		public static function send_heartbeat(): void {
			$license_key = get_option( self::OPTION_KEY, '' );

			if ( empty( $license_key ) ) {
				return;
			}

			$response = self::api_request( '/heartbeat', [
				'license_key'    => $license_key,
				'domain'         => self::current_domain(),
				'wp_version'     => get_bloginfo( 'version' ),
				'plugin_version' => defined( 'TLAT_VERSION' ) ? TLAT_VERSION : '1.0.0',
			] );

			// If heartbeat indicates license is no longer valid, start grace period
			if ( ! is_wp_error( $response ) && isset( $response['valid'] ) ) {
				if ( $response['valid'] ) {
					// License is valid, end grace period
					self::end_grace_period();
				} else {
					// License invalid, start grace period
					self::start_grace_period();
					update_option( self::OPTION_STATUS, [
						'status'  => 'expired',
						'message' => 'License has expired',
						'valid'   => false,
						'checked' => time(),
					] );
				}
			}
		}

		/**
		 * Check if current license is valid (including grace period)
		 *
		 * @return bool Whether license is valid or in grace period
		 */
		public static function is_valid(): bool {
			$status = get_option( self::OPTION_STATUS );
			
			// Check if license is actually valid
			if ( is_array( $status ) && ! empty( $status['valid'] ) ) {
				// License is valid, clear any grace period
				delete_option( self::OPTION_GRACE_START );
				return true;
			}
			
			// License is not valid, but check grace period
			return self::is_in_grace_period();
		}

		/**
		 * Check if license is strictly valid (not counting grace period)
		 *
		 * @return bool Whether license is actually valid
		 */
		public static function is_strictly_valid(): bool {
			$status = get_option( self::OPTION_STATUS );
			return is_array( $status ) && ! empty( $status['valid'] );
		}

		/**
		 * Check if currently in grace period
		 *
		 * @return bool Whether in grace period
		 */
		public static function is_in_grace_period(): bool {
			$grace_start = get_option( self::OPTION_GRACE_START, 0 );
			
			if ( empty( $grace_start ) ) {
				return false;
			}
			
			$grace_end = $grace_start + self::GRACE_PERIOD_SECONDS;
			
			return time() < $grace_end;
		}

		/**
		 * Get grace period info
		 *
		 * @return array Grace period details or empty if not in grace
		 */
		public static function get_grace_period_info(): array {
			$grace_start = get_option( self::OPTION_GRACE_START, 0 );
			
			if ( empty( $grace_start ) ) {
				return [];
			}
			
			$grace_end     = $grace_start + self::GRACE_PERIOD_SECONDS;
			$now           = time();
			$remaining     = max( 0, $grace_end - $now );
			$days_left     = ceil( $remaining / DAY_IN_SECONDS );
			$is_active     = $remaining > 0;
			
			return [
				'active'       => $is_active,
				'started'      => $grace_start,
				'ends'         => $grace_end,
				'remaining'    => $remaining,
				'days_left'    => $days_left,
				'total_days'   => self::GRACE_PERIOD_DAYS,
				'days_elapsed' => self::GRACE_PERIOD_DAYS - $days_left,
			];
		}

		/**
		 * Start grace period (called when license becomes invalid)
		 */
		private static function start_grace_period(): void {
			// Only start if not already in grace period
			if ( ! get_option( self::OPTION_GRACE_START ) ) {
				update_option( self::OPTION_GRACE_START, time() );
			}
		}

		/**
		 * End grace period (called when license becomes valid again)
		 */
		private static function end_grace_period(): void {
			delete_option( self::OPTION_GRACE_START );
		}

		/**
		 * Get current license status
		 *
		 * @return array Status array with status, message, valid, checked
		 */
		public static function get_status(): array {
			$status = get_option( self::OPTION_STATUS );
			return is_array( $status ) ? $status : [
				'status'  => 'unknown',
				'message' => 'Status not checked',
				'valid'   => false,
				'checked' => 0,
			];
		}

		/**
		 * Render admin notice for license issues
		 */
		public static function render_notice(): void {
			// Only show on plugin-related pages
			$screen = get_current_screen();
			if ( ! $screen || strpos( $screen->id, 'tutor' ) === false ) {
				// Also check if on plugins page
				if ( ! $screen || $screen->id !== 'plugins' ) {
					return;
				}
			}

			$status      = self::get_status();
			$grace_info  = self::get_grace_period_info();

			// Don't show notice if license is strictly valid
			if ( ! empty( $status['valid'] ) ) {
				return;
			}

			$status_text = $status['status'] ?? 'unknown';
			$message     = $status['message'] ?? '';

			// Check if in grace period
			if ( ! empty( $grace_info['active'] ) ) {
				$days_left = $grace_info['days_left'];
				$notice_class = $days_left <= 3 ? 'notice-error' : 'notice-warning';
				?>
				<div class="notice <?php echo esc_attr( $notice_class ); ?>">
					<p>
						<strong>⏳ Tutor LMS Advanced Tracking — Grace Period:</strong>
						<?php
						if ( $days_left <= 1 ) {
							esc_html_e( 'Your license grace period expires TODAY! Please renew or reactivate your license to continue using the plugin.', 'tutor-lms-advanced-tracking' );
						} else {
							echo esc_html( sprintf(
								/* translators: %d: number of days */
								_n(
									'Your license is invalid but the plugin will continue working for %d more day. Please renew or reactivate.',
									'Your license is invalid but the plugin will continue working for %d more days. Please renew or reactivate.',
									$days_left,
									'tutor-lms-advanced-tracking'
								),
								$days_left
							) );
						}
						?>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tlat-license' ) ); ?>">
							<?php esc_html_e( 'Manage License →', 'tutor-lms-advanced-tracking' ); ?>
						</a>
					</p>
				</div>
				<?php
				return;
			}

			// Grace period expired or no grace period
			$notice_class = 'notice-warning';
			if ( in_array( $status_text, [ 'expired', 'invalid_key', 'grace_expired' ], true ) ) {
				$notice_class = 'notice-error';
			}

			?>
			<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
				<p>
					<strong>Tutor LMS Advanced Tracking:</strong>
					<?php
					switch ( $status_text ) {
						case 'missing':
							esc_html_e( 'License key not entered. Please enter your license key in Settings → TLAT License.', 'tutor-lms-advanced-tracking' );
							break;
						case 'expired':
						case 'grace_expired':
							esc_html_e( 'Your license has expired and the grace period has ended. Please renew to continue using all features.', 'tutor-lms-advanced-tracking' );
							break;
						case 'invalid_key':
							esc_html_e( 'Invalid license key. Please check and re-enter your license.', 'tutor-lms-advanced-tracking' );
							break;
						case 'limit_reached':
							esc_html_e( 'Activation limit reached. Please deactivate from another site or upgrade your license.', 'tutor-lms-advanced-tracking' );
							break;
						default:
							echo esc_html( sprintf(
								/* translators: 1: status, 2: message */
								__( 'License status: %1$s — %2$s', 'tutor-lms-advanced-tracking' ),
								$status_text,
								$message
							) );
					}
					?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tlat-license' ) ); ?>">
						<?php esc_html_e( 'Manage License →', 'tutor-lms-advanced-tracking' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		/**
		 * Cleanup on plugin deactivation
		 */
		public static function deactivate_plugin(): void {
			// Clear scheduled heartbeat
			wp_clear_scheduled_hook( self::CRON_HOOK );

			// Optionally deactivate license (uncomment if desired)
			// self::deactivate();
		}
	}
}
