<?php
/**
 * License Activation Tracking
 *
 * Tracks license activation/deactivation events and displays
 * activation history in the admin interface.
 *
 * @package TutorLMSAdvancedTracking
 * @since 1.2.0
 */

if ( ! class_exists( 'TLAT_Activation_Tracking' ) ) {
	class TLAT_Activation_Tracking {
		// Option key for storing activation history
		const OPTION_HISTORY = 'tlat_activation_history';

		// Max history entries to keep
		const MAX_HISTORY = 50;

		// Event types
		const EVENT_ACTIVATED   = 'activated';
		const EVENT_DEACTIVATED = 'deactivated';
		const EVENT_FAILED      = 'failed';
		const EVENT_VALIDATED   = 'validated';
		const EVENT_EXPIRED     = 'expired';
		const EVENT_GRACE_START = 'grace_started';
		const EVENT_GRACE_END   = 'grace_ended';

		/**
		 * Initialize hooks
		 */
		public static function init(): void {
			// Add AJAX endpoint for viewing history
			add_action( 'wp_ajax_tlat_get_activation_history', [ __CLASS__, 'ajax_get_history' ] );
		}

		/**
		 * Log an activation event
		 *
		 * @param string $event_type Event type (see constants)
		 * @param array  $data       Additional event data
		 */
		public static function log_event( string $event_type, array $data = [] ): void {
			$history = self::get_history();

			$entry = [
				'type'      => $event_type,
				'timestamp' => time(),
				'domain'    => TLAT_License_Validator::current_domain(),
				'ip'        => self::get_client_ip(),
				'user_id'   => get_current_user_id(),
				'user'      => wp_get_current_user()->user_login ?: 'system',
			];

			// Merge additional data
			$entry = array_merge( $entry, $data );

			// Add to beginning of array
			array_unshift( $history, $entry );

			// Trim to max entries
			if ( count( $history ) > self::MAX_HISTORY ) {
				$history = array_slice( $history, 0, self::MAX_HISTORY );
			}

			update_option( self::OPTION_HISTORY, $history );
		}

		/**
		 * Log successful activation
		 *
		 * @param string $license_key License key (partially masked)
		 * @param array  $response    Server response data
		 */
		public static function log_activation( string $license_key, array $response = [] ): void {
			self::log_event( self::EVENT_ACTIVATED, [
				'license_key' => self::mask_key( $license_key ),
				'plan'        => $response['plan'] ?? null,
				'expires'     => $response['expiresAt'] ?? null,
				'remaining'   => $response['remaining'] ?? null,
			] );
		}

		/**
		 * Log deactivation
		 *
		 * @param string $license_key License key (partially masked)
		 */
		public static function log_deactivation( string $license_key ): void {
			self::log_event( self::EVENT_DEACTIVATED, [
				'license_key' => self::mask_key( $license_key ),
			] );
		}

		/**
		 * Log failed activation attempt
		 *
		 * @param string $license_key License key (partially masked)
		 * @param string $reason      Failure reason
		 */
		public static function log_failure( string $license_key, string $reason ): void {
			self::log_event( self::EVENT_FAILED, [
				'license_key' => self::mask_key( $license_key ),
				'reason'      => $reason,
			] );
		}

		/**
		 * Log validation event
		 *
		 * @param bool   $is_valid Whether validation succeeded
		 * @param string $status   Status code
		 */
		public static function log_validation( bool $is_valid, string $status ): void {
			// Only log validation events that indicate a state change
			if ( $is_valid ) {
				return; // Don't log routine successful validations
			}

			self::log_event( self::EVENT_VALIDATED, [
				'valid'  => $is_valid,
				'status' => $status,
			] );
		}

		/**
		 * Log license expiration
		 */
		public static function log_expiration(): void {
			self::log_event( self::EVENT_EXPIRED );
		}

		/**
		 * Log grace period start
		 *
		 * @param int $days_remaining Days remaining in grace period
		 */
		public static function log_grace_start( int $days_remaining ): void {
			self::log_event( self::EVENT_GRACE_START, [
				'days_remaining' => $days_remaining,
			] );
		}

		/**
		 * Log grace period end
		 *
		 * @param string $reason How grace ended ('renewed', 'expired', 'deactivated')
		 */
		public static function log_grace_end( string $reason ): void {
			self::log_event( self::EVENT_GRACE_END, [
				'reason' => $reason,
			] );
		}

		/**
		 * Get activation history
		 *
		 * @param int $limit Number of entries to return (0 = all)
		 * @return array History entries
		 */
		public static function get_history( int $limit = 0 ): array {
			$history = get_option( self::OPTION_HISTORY, [] );
			
			if ( ! is_array( $history ) ) {
				return [];
			}

			if ( $limit > 0 && count( $history ) > $limit ) {
				return array_slice( $history, 0, $limit );
			}

			return $history;
		}

		/**
		 * Get statistics summary
		 *
		 * @return array Statistics
		 */
		public static function get_stats(): array {
			$history = self::get_history();

			$stats = [
				'total_events'     => count( $history ),
				'activations'      => 0,
				'deactivations'    => 0,
				'failures'         => 0,
				'last_activation'  => null,
				'first_activation' => null,
				'days_active'      => 0,
			];

			$first_activation_time = null;
			$last_activation_time  = null;

			foreach ( $history as $entry ) {
				switch ( $entry['type'] ?? '' ) {
					case self::EVENT_ACTIVATED:
						$stats['activations']++;
						if ( ! $last_activation_time ) {
							$last_activation_time = $entry['timestamp'];
						}
						$first_activation_time = $entry['timestamp'];
						break;
					case self::EVENT_DEACTIVATED:
						$stats['deactivations']++;
						break;
					case self::EVENT_FAILED:
						$stats['failures']++;
						break;
				}
			}

			if ( $last_activation_time ) {
				$stats['last_activation'] = $last_activation_time;
			}
			if ( $first_activation_time ) {
				$stats['first_activation'] = $first_activation_time;
				$stats['days_active'] = floor( ( time() - $first_activation_time ) / DAY_IN_SECONDS );
			}

			return $stats;
		}

		/**
		 * Clear all history
		 */
		public static function clear_history(): void {
			delete_option( self::OPTION_HISTORY );
		}

		/**
		 * AJAX handler to get history
		 */
		public static function ajax_get_history(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Permission denied' );
			}

			check_ajax_referer( 'tlat_activation_history', 'nonce' );

			$limit   = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;
			$history = self::get_history( $limit );
			$stats   = self::get_stats();

			wp_send_json_success( [
				'history' => $history,
				'stats'   => $stats,
			] );
		}

		/**
		 * Mask license key for storage
		 *
		 * @param string $key Full license key
		 * @return string Partially masked key
		 */
		private static function mask_key( string $key ): string {
			if ( strlen( $key ) < 10 ) {
				return str_repeat( '*', strlen( $key ) );
			}

			return substr( $key, 0, 9 ) . str_repeat( '•', strlen( $key ) - 13 ) . substr( $key, -4 );
		}

		/**
		 * Get client IP address
		 *
		 * @return string IP address
		 */
		private static function get_client_ip(): string {
			$ip_keys = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];

			foreach ( $ip_keys as $key ) {
				if ( ! empty( $_SERVER[ $key ] ) ) {
					$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
					// Handle comma-separated IPs (from proxies)
					if ( strpos( $ip, ',' ) !== false ) {
						$ip = trim( explode( ',', $ip )[0] );
					}
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						return $ip;
					}
				}
			}

			return 'unknown';
		}

		/**
		 * Render activation history UI component
		 * 
		 * @return string HTML output
		 */
		public static function render_history_ui(): string {
			$history = self::get_history( 10 );
			$stats   = self::get_stats();

			ob_start();
			?>
			<div class="tlat-activation-history">
				<h3 style="margin-top:0;">
					📊 <?php esc_html_e( 'Activation History', 'tutor-lms-advanced-tracking' ); ?>
				</h3>

				<!-- Quick Stats -->
				<div class="tlat-activation-stats" style="display:grid;grid-template-columns:repeat(3, 1fr);gap:15px;margin-bottom:20px;">
					<div style="background:#f0f0f1;padding:15px;border-radius:4px;text-align:center;">
						<div style="font-size:24px;font-weight:700;color:#2271b1;"><?php echo esc_html( $stats['activations'] ); ?></div>
						<div style="font-size:12px;color:#666;"><?php esc_html_e( 'Activations', 'tutor-lms-advanced-tracking' ); ?></div>
					</div>
					<div style="background:#f0f0f1;padding:15px;border-radius:4px;text-align:center;">
						<div style="font-size:24px;font-weight:700;color:#135e96;"><?php echo esc_html( $stats['days_active'] ); ?></div>
						<div style="font-size:12px;color:#666;"><?php esc_html_e( 'Days Active', 'tutor-lms-advanced-tracking' ); ?></div>
					</div>
					<div style="background:#f0f0f1;padding:15px;border-radius:4px;text-align:center;">
						<div style="font-size:24px;font-weight:700;color:<?php echo $stats['failures'] > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo esc_html( $stats['failures'] ); ?></div>
						<div style="font-size:12px;color:#666;"><?php esc_html_e( 'Failed Attempts', 'tutor-lms-advanced-tracking' ); ?></div>
					</div>
				</div>

				<!-- Event Log -->
				<div class="tlat-event-log" style="max-height:300px;overflow-y:auto;border:1px solid #dcdcde;border-radius:4px;">
					<?php if ( empty( $history ) ) : ?>
						<p style="padding:20px;text-align:center;color:#666;margin:0;">
							<?php esc_html_e( 'No activation history yet.', 'tutor-lms-advanced-tracking' ); ?>
						</p>
					<?php else : ?>
						<table style="width:100%;border-collapse:collapse;font-size:13px;">
							<thead style="background:#f6f7f7;position:sticky;top:0;">
								<tr>
									<th style="padding:10px;text-align:left;border-bottom:1px solid #dcdcde;"><?php esc_html_e( 'Event', 'tutor-lms-advanced-tracking' ); ?></th>
									<th style="padding:10px;text-align:left;border-bottom:1px solid #dcdcde;"><?php esc_html_e( 'Details', 'tutor-lms-advanced-tracking' ); ?></th>
									<th style="padding:10px;text-align:right;border-bottom:1px solid #dcdcde;"><?php esc_html_e( 'When', 'tutor-lms-advanced-tracking' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $history as $event ) : ?>
									<?php
									$icon        = self::get_event_icon( $event['type'] ?? '' );
									$event_label = self::get_event_label( $event['type'] ?? '' );
									$details     = self::format_event_details( $event );
									$time_ago    = human_time_diff( $event['timestamp'] ?? time(), time() );
									$row_color   = self::get_event_row_color( $event['type'] ?? '' );
									?>
									<tr style="<?php echo esc_attr( $row_color ); ?>">
										<td style="padding:10px;border-bottom:1px solid #f0f0f1;">
											<?php echo esc_html( $icon . ' ' . $event_label ); ?>
										</td>
										<td style="padding:10px;border-bottom:1px solid #f0f0f1;color:#666;">
											<?php echo esc_html( $details ); ?>
										</td>
										<td style="padding:10px;border-bottom:1px solid #f0f0f1;text-align:right;white-space:nowrap;">
											<span title="<?php echo esc_attr( date_i18n( 'Y-m-d H:i:s', $event['timestamp'] ?? time() ) ); ?>">
												<?php echo esc_html( $time_ago . ' ' . __( 'ago', 'tutor-lms-advanced-tracking' ) ); ?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<?php if ( count( $history ) >= 10 && $stats['total_events'] > 10 ) : ?>
					<p style="margin-top:10px;font-size:12px;color:#666;">
						<?php
						echo esc_html( sprintf(
							/* translators: %d: total number of events */
							__( 'Showing 10 of %d events.', 'tutor-lms-advanced-tracking' ),
							$stats['total_events']
						) );
						?>
					</p>
				<?php endif; ?>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Get icon for event type
		 *
		 * @param string $type Event type
		 * @return string Emoji icon
		 */
		private static function get_event_icon( string $type ): string {
			$icons = [
				self::EVENT_ACTIVATED   => '✅',
				self::EVENT_DEACTIVATED => '🔓',
				self::EVENT_FAILED      => '❌',
				self::EVENT_VALIDATED   => '🔄',
				self::EVENT_EXPIRED     => '⏰',
				self::EVENT_GRACE_START => '⏳',
				self::EVENT_GRACE_END   => '⚡',
			];

			return $icons[ $type ] ?? '📝';
		}

		/**
		 * Get human-readable label for event type
		 *
		 * @param string $type Event type
		 * @return string Label
		 */
		private static function get_event_label( string $type ): string {
			$labels = [
				self::EVENT_ACTIVATED   => __( 'Activated', 'tutor-lms-advanced-tracking' ),
				self::EVENT_DEACTIVATED => __( 'Deactivated', 'tutor-lms-advanced-tracking' ),
				self::EVENT_FAILED      => __( 'Failed', 'tutor-lms-advanced-tracking' ),
				self::EVENT_VALIDATED   => __( 'Validation', 'tutor-lms-advanced-tracking' ),
				self::EVENT_EXPIRED     => __( 'Expired', 'tutor-lms-advanced-tracking' ),
				self::EVENT_GRACE_START => __( 'Grace Started', 'tutor-lms-advanced-tracking' ),
				self::EVENT_GRACE_END   => __( 'Grace Ended', 'tutor-lms-advanced-tracking' ),
			];

			return $labels[ $type ] ?? ucfirst( $type );
		}

		/**
		 * Get row background color for event type
		 *
		 * @param string $type Event type
		 * @return string CSS style
		 */
		private static function get_event_row_color( string $type ): string {
			$colors = [
				self::EVENT_ACTIVATED => 'background:#f0fff4;',
				self::EVENT_FAILED    => 'background:#fff5f5;',
				self::EVENT_EXPIRED   => 'background:#fffdf0;',
			];

			return $colors[ $type ] ?? '';
		}

		/**
		 * Format event details for display
		 *
		 * @param array $event Event data
		 * @return string Formatted details
		 */
		private static function format_event_details( array $event ): string {
			$type = $event['type'] ?? '';
			$parts = [];

			// Add license key if present
			if ( ! empty( $event['license_key'] ) ) {
				$parts[] = $event['license_key'];
			}

			// Add reason if present
			if ( ! empty( $event['reason'] ) ) {
				$parts[] = $event['reason'];
			}

			// Add user if present and not system
			if ( ! empty( $event['user'] ) && $event['user'] !== 'system' ) {
				$parts[] = sprintf( __( 'by %s', 'tutor-lms-advanced-tracking' ), $event['user'] );
			}

			// Add plan if present
			if ( ! empty( $event['plan'] ) ) {
				$parts[] = sprintf( __( 'Plan: %s', 'tutor-lms-advanced-tracking' ), ucfirst( $event['plan'] ) );
			}

			// Add days remaining for grace start
			if ( $type === self::EVENT_GRACE_START && isset( $event['days_remaining'] ) ) {
				$parts[] = sprintf(
					/* translators: %d: number of days */
					_n( '%d day remaining', '%d days remaining', $event['days_remaining'], 'tutor-lms-advanced-tracking' ),
					$event['days_remaining']
				);
			}

			return implode( ' · ', $parts ) ?: '—';
		}
	}
}
