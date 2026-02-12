<?php
/**
 * License Settings Admin Page
 *
 * Provides a settings page for license key activation/deactivation
 * in the WordPress admin area under Settings → TLAT License.
 *
 * @package TutorLMSAdvancedTracking
 * @since 1.1.0
 */

if ( ! class_exists( 'TLAT_License_Settings' ) ) {
	class TLAT_License_Settings {
		// Page slug
		const PAGE_SLUG = 'tlat-license';

		// Nonce action
		const NONCE_ACTION = 'tlat_license_action';

		/**
		 * Initialize hooks
		 */
		public static function init(): void {
			add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
			add_action( 'admin_init', [ __CLASS__, 'handle_form_submission' ] );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
		}

		/**
		 * Add menu page under Settings
		 */
		public static function add_menu_page(): void {
			add_options_page(
				__( 'TLAT License', 'tutor-lms-advanced-tracking' ),
				__( 'TLAT License', 'tutor-lms-advanced-tracking' ),
				'manage_options',
				self::PAGE_SLUG,
				[ __CLASS__, 'render_page' ]
			);
		}

		/**
		 * Enqueue admin styles
		 */
		public static function enqueue_styles( string $hook ): void {
			if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
				return;
			}

			wp_add_inline_style( 'wp-admin', '
				.tlat-license-wrap {
					max-width: 600px;
					margin-top: 20px;
				}
				.tlat-license-box {
					background: #fff;
					border: 1px solid #c3c4c7;
					border-radius: 4px;
					padding: 20px;
					margin-bottom: 20px;
				}
				.tlat-license-status {
					display: flex;
					align-items: center;
					gap: 12px;
					margin-bottom: 20px;
					padding: 15px;
					border-radius: 4px;
				}
				.tlat-license-status.valid {
					background: #d1e7dd;
					border: 1px solid #a3cfbb;
				}
				.tlat-license-status.invalid {
					background: #f8d7da;
					border: 1px solid #f1aeb5;
				}
				.tlat-license-status.warning {
					background: #fff3cd;
					border: 1px solid #ffe69c;
				}
				.tlat-license-status-icon {
					font-size: 24px;
				}
				.tlat-license-status-text h3 {
					margin: 0 0 4px 0;
					font-size: 15px;
				}
				.tlat-license-status-text p {
					margin: 0;
					color: #666;
					font-size: 13px;
				}
				.tlat-license-form input[type="text"] {
					width: 100%;
					font-family: monospace;
					font-size: 14px;
					padding: 10px;
				}
				.tlat-license-form .button {
					margin-top: 10px;
				}
				.tlat-license-info {
					margin-top: 20px;
					padding: 15px;
					background: #f0f0f1;
					border-radius: 4px;
				}
				.tlat-license-info h4 {
					margin: 0 0 10px 0;
				}
				.tlat-license-info dl {
					margin: 0;
				}
				.tlat-license-info dt {
					font-weight: 600;
					margin-top: 8px;
				}
				.tlat-license-info dd {
					margin: 2px 0 0 0;
					color: #666;
				}
				.tlat-license-actions {
					margin-top: 15px;
					padding-top: 15px;
					border-top: 1px solid #dcdcde;
				}
			' );
		}

		/**
		 * Handle form submission
		 */
		public static function handle_form_submission(): void {
			if ( ! isset( $_POST['tlat_license_nonce'] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['tlat_license_nonce'], self::NONCE_ACTION ) ) {
				wp_die( __( 'Security check failed', 'tutor-lms-advanced-tracking' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'Permission denied', 'tutor-lms-advanced-tracking' ) );
			}

			$action = isset( $_POST['tlat_action'] ) ? sanitize_key( $_POST['tlat_action'] ) : '';

			switch ( $action ) {
				case 'activate':
					$license_key = isset( $_POST['tlat_license_key'] ) 
						? sanitize_text_field( wp_unslash( $_POST['tlat_license_key'] ) ) 
						: '';

					if ( empty( $license_key ) ) {
						add_settings_error(
							'tlat_license',
							'empty_key',
							__( 'Please enter a license key.', 'tutor-lms-advanced-tracking' ),
							'error'
						);
						return;
					}

					$result = TLAT_License_Validator::activate( $license_key );

					if ( $result['success'] ) {
						add_settings_error(
							'tlat_license',
							'activated',
							$result['message'],
							'success'
						);
					} else {
						add_settings_error(
							'tlat_license',
							'activation_failed',
							$result['message'],
							'error'
						);
					}
					break;

				case 'deactivate':
					$result = TLAT_License_Validator::deactivate();

					if ( $result['success'] ) {
						add_settings_error(
							'tlat_license',
							'deactivated',
							$result['message'],
							'success'
						);
					} else {
						add_settings_error(
							'tlat_license',
							'deactivation_failed',
							$result['message'],
							'error'
						);
					}
					break;

				case 'refresh':
					$result = TLAT_License_Validator::validate();
					add_settings_error(
						'tlat_license',
						'refreshed',
						__( 'License status refreshed.', 'tutor-lms-advanced-tracking' ),
						'info'
					);
					break;

				case 'set_server':
					$server_url = isset( $_POST['tlat_server_url'] ) 
						? esc_url_raw( wp_unslash( $_POST['tlat_server_url'] ) ) 
						: '';

					if ( ! empty( $server_url ) ) {
						update_option( TLAT_License_Validator::OPTION_SERVER_URL, $server_url );
						add_settings_error(
							'tlat_license',
							'server_updated',
							__( 'License server URL updated.', 'tutor-lms-advanced-tracking' ),
							'success'
						);
					}
					break;
			}
		}

		/**
		 * Render the settings page
		 */
		public static function render_page(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'Permission denied', 'tutor-lms-advanced-tracking' ) );
			}

			$status      = TLAT_License_Validator::get_status();
			$is_valid    = ! empty( $status['valid'] );
			$license_key = get_option( TLAT_License_Validator::OPTION_KEY, '' );
			$server_url  = TLAT_License_Validator::get_server_url();

			// Determine status display
			$status_class = 'warning';
			$status_icon  = '⚠️';
			$status_title = __( 'No License', 'tutor-lms-advanced-tracking' );
			$status_desc  = __( 'Enter your license key to activate.', 'tutor-lms-advanced-tracking' );

			if ( $is_valid ) {
				$status_class = 'valid';
				$status_icon  = '✅';
				$status_title = __( 'License Active', 'tutor-lms-advanced-tracking' );
				$status_desc  = $status['message'] ?? __( 'Your license is valid and active.', 'tutor-lms-advanced-tracking' );
			} elseif ( ! empty( $license_key ) ) {
				$status_class = 'invalid';
				$status_icon  = '❌';
				$status_title = __( 'License Invalid', 'tutor-lms-advanced-tracking' );
				$status_desc  = $status['message'] ?? __( 'There is an issue with your license.', 'tutor-lms-advanced-tracking' );
			}

			// Last checked time
			$last_checked = '';
			if ( ! empty( $status['checked'] ) ) {
				$last_checked = human_time_diff( $status['checked'], time() ) . ' ' . __( 'ago', 'tutor-lms-advanced-tracking' );
			}

			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Tutor LMS Advanced Tracking — License', 'tutor-lms-advanced-tracking' ); ?></h1>

				<?php settings_errors( 'tlat_license' ); ?>

				<div class="tlat-license-wrap">
					<!-- Status Box -->
					<div class="tlat-license-box">
						<div class="tlat-license-status <?php echo esc_attr( $status_class ); ?>">
							<span class="tlat-license-status-icon"><?php echo esc_html( $status_icon ); ?></span>
							<div class="tlat-license-status-text">
								<h3><?php echo esc_html( $status_title ); ?></h3>
								<p><?php echo esc_html( $status_desc ); ?></p>
							</div>
						</div>

						<?php if ( $is_valid ) : ?>
							<!-- Active license info -->
							<?php if ( ! empty( $status['license'] ) ) : $lic = $status['license']; ?>
							<div class="tlat-license-info">
								<h4><?php esc_html_e( 'License Details', 'tutor-lms-advanced-tracking' ); ?></h4>
								<dl>
									<?php if ( ! empty( $lic['plan'] ) ) : ?>
									<dt><?php esc_html_e( 'Plan', 'tutor-lms-advanced-tracking' ); ?></dt>
									<dd><?php echo esc_html( ucfirst( $lic['plan'] ) ); ?></dd>
									<?php endif; ?>

									<?php if ( ! empty( $lic['expiresAt'] ) ) : ?>
									<dt><?php esc_html_e( 'Expires', 'tutor-lms-advanced-tracking' ); ?></dt>
									<dd><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $lic['expiresAt'] ) ) ); ?></dd>
									<?php endif; ?>

									<?php if ( isset( $lic['currentActivations'], $lic['maxActivations'] ) ) : ?>
									<dt><?php esc_html_e( 'Activations', 'tutor-lms-advanced-tracking' ); ?></dt>
									<dd><?php echo esc_html( $lic['currentActivations'] . ' / ' . $lic['maxActivations'] ); ?></dd>
									<?php endif; ?>
								</dl>
							</div>
							<?php endif; ?>

							<!-- Deactivate form -->
							<div class="tlat-license-actions">
								<form method="post" class="tlat-license-form">
									<?php wp_nonce_field( self::NONCE_ACTION, 'tlat_license_nonce' ); ?>
									<input type="hidden" name="tlat_action" value="deactivate">
									<p>
										<strong><?php esc_html_e( 'Current Key:', 'tutor-lms-advanced-tracking' ); ?></strong>
										<code><?php echo esc_html( substr( $license_key, 0, 9 ) . '••••••••••••' ); ?></code>
									</p>
									<button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to deactivate this license?', 'tutor-lms-advanced-tracking' ); ?>');">
										<?php esc_html_e( 'Deactivate License', 'tutor-lms-advanced-tracking' ); ?>
									</button>
									<button type="submit" name="tlat_action" value="refresh" class="button button-link">
										<?php esc_html_e( 'Refresh Status', 'tutor-lms-advanced-tracking' ); ?>
									</button>
								</form>
								<?php if ( $last_checked ) : ?>
								<p style="margin-top:10px;color:#666;font-size:12px;">
									<?php echo esc_html( sprintf( __( 'Last checked: %s', 'tutor-lms-advanced-tracking' ), $last_checked ) ); ?>
								</p>
								<?php endif; ?>
							</div>

						<?php else : ?>
							<!-- Activate form -->
							<form method="post" class="tlat-license-form">
								<?php wp_nonce_field( self::NONCE_ACTION, 'tlat_license_nonce' ); ?>
								<input type="hidden" name="tlat_action" value="activate">
								<p>
									<label for="tlat_license_key">
										<strong><?php esc_html_e( 'License Key', 'tutor-lms-advanced-tracking' ); ?></strong>
									</label>
								</p>
								<p>
									<input 
										type="text" 
										id="tlat_license_key" 
										name="tlat_license_key" 
										value="<?php echo esc_attr( $license_key ); ?>"
										placeholder="TLAT-XXXX-XXXX-XXXX-XXXX"
										autocomplete="off"
										spellcheck="false"
									>
								</p>
								<p>
									<button type="submit" class="button button-primary">
										<?php esc_html_e( 'Activate License', 'tutor-lms-advanced-tracking' ); ?>
									</button>
								</p>
							</form>

							<p style="margin-top:15px;color:#666;font-size:13px;">
								<?php
								printf(
									/* translators: %s: purchase link */
									esc_html__( 'Don\'t have a license? %s', 'tutor-lms-advanced-tracking' ),
									'<a href="https://mahope.dk/plugins/tutor-lms-advanced-tracking" target="_blank">' . 
									esc_html__( 'Purchase one here', 'tutor-lms-advanced-tracking' ) . 
									'</a>'
								);
								?>
							</p>
						<?php endif; ?>
					</div>

					<!-- Advanced Settings (collapsible) -->
					<details class="tlat-license-box">
						<summary style="cursor:pointer;font-weight:600;">
							<?php esc_html_e( 'Advanced Settings', 'tutor-lms-advanced-tracking' ); ?>
						</summary>
						<form method="post" class="tlat-license-form" style="margin-top:15px;">
							<?php wp_nonce_field( self::NONCE_ACTION, 'tlat_license_nonce' ); ?>
							<input type="hidden" name="tlat_action" value="set_server">
							<p>
								<label for="tlat_server_url">
									<strong><?php esc_html_e( 'License Server URL', 'tutor-lms-advanced-tracking' ); ?></strong>
								</label>
							</p>
							<p>
								<input 
									type="text" 
									id="tlat_server_url" 
									name="tlat_server_url" 
									value="<?php echo esc_attr( $server_url ); ?>"
									placeholder="https://license.mahope.dk"
								>
							</p>
							<p style="color:#666;font-size:12px;">
								<?php esc_html_e( 'Only change this if instructed by support.', 'tutor-lms-advanced-tracking' ); ?>
							</p>
							<p>
								<button type="submit" class="button">
									<?php esc_html_e( 'Save Server URL', 'tutor-lms-advanced-tracking' ); ?>
								</button>
							</p>
						</form>
					</details>
				</div>
			</div>
			<?php
		}
	}
}
