<?php
/**
 * Unit tests for TLAT_License_Validator
 *
 * @package TutorLMSAdvancedTracking
 */

namespace TLAT\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TLAT_License_Validator;

class LicenseValidatorTest extends TestCase {

    /**
     * Reset mock options before each test
     */
    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_options;
        $wp_mock_options = [];
    }

    /**
     * Test domain normalization
     */
    public function test_current_domain_normalizes_correctly(): void {
        $domain = TLAT_License_Validator::current_domain();

        // Should return example.com from home_url mock
        $this->assertEquals( 'example.com', $domain );
    }

    /**
     * Test default server URL
     */
    public function test_get_server_url_returns_default(): void {
        $url = TLAT_License_Validator::get_server_url();

        $this->assertEquals( 'https://license.mahope.dk', $url );
    }

    /**
     * Test custom server URL
     */
    public function test_get_server_url_returns_custom_when_set(): void {
        global $wp_mock_options;
        $wp_mock_options[ TLAT_License_Validator::OPTION_SERVER_URL ] = 'https://custom.server.com/';

        $url = TLAT_License_Validator::get_server_url();

        $this->assertEquals( 'https://custom.server.com', $url );
    }

    /**
     * Test is_valid returns false when no license
     */
    public function test_is_valid_returns_false_when_no_license(): void {
        $this->assertFalse( TLAT_License_Validator::is_valid() );
    }

    /**
     * Test is_valid returns true when status is valid
     */
    public function test_is_valid_returns_true_when_status_valid(): void {
        global $wp_mock_options;
        $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ] = [
            'status' => 'valid',
            'valid'  => true,
        ];

        $this->assertTrue( TLAT_License_Validator::is_valid() );
    }

    /**
     * Test is_strictly_valid
     */
    public function test_is_strictly_valid_returns_true_only_when_valid(): void {
        global $wp_mock_options;

        // No status set
        $this->assertFalse( TLAT_License_Validator::is_strictly_valid() );

        // Status invalid
        $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ] = [
            'status' => 'expired',
            'valid'  => false,
        ];
        $this->assertFalse( TLAT_License_Validator::is_strictly_valid() );

        // Status valid
        $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ] = [
            'status' => 'valid',
            'valid'  => true,
        ];
        $this->assertTrue( TLAT_License_Validator::is_strictly_valid() );
    }

    /**
     * Test grace period is not active by default
     */
    public function test_is_in_grace_period_returns_false_by_default(): void {
        $this->assertFalse( TLAT_License_Validator::is_in_grace_period() );
    }

    /**
     * Test grace period detection
     */
    public function test_is_in_grace_period_returns_true_when_active(): void {
        global $wp_mock_options;

        // Set grace start to now (within 14 days)
        $wp_mock_options[ TLAT_License_Validator::OPTION_GRACE_START ] = time();

        $this->assertTrue( TLAT_License_Validator::is_in_grace_period() );
    }

    /**
     * Test expired grace period
     */
    public function test_is_in_grace_period_returns_false_when_expired(): void {
        global $wp_mock_options;

        // Set grace start to 15 days ago (past 14-day grace)
        $wp_mock_options[ TLAT_License_Validator::OPTION_GRACE_START ] = time() - ( 15 * DAY_IN_SECONDS );

        $this->assertFalse( TLAT_License_Validator::is_in_grace_period() );
    }

    /**
     * Test grace period info structure
     */
    public function test_get_grace_period_info_returns_correct_structure(): void {
        global $wp_mock_options;

        // No grace period
        $info = TLAT_License_Validator::get_grace_period_info();
        $this->assertEmpty( $info );

        // Active grace period (started 2 days ago)
        $two_days_ago = time() - ( 2 * DAY_IN_SECONDS );
        $wp_mock_options[ TLAT_License_Validator::OPTION_GRACE_START ] = $two_days_ago;

        $info = TLAT_License_Validator::get_grace_period_info();

        $this->assertArrayHasKey( 'active', $info );
        $this->assertArrayHasKey( 'started', $info );
        $this->assertArrayHasKey( 'ends', $info );
        $this->assertArrayHasKey( 'remaining', $info );
        $this->assertArrayHasKey( 'days_left', $info );
        $this->assertArrayHasKey( 'total_days', $info );
        $this->assertArrayHasKey( 'days_elapsed', $info );

        $this->assertTrue( $info['active'] );
        $this->assertEquals( $two_days_ago, $info['started'] );
        $this->assertEquals( 14, $info['total_days'] );
        $this->assertGreaterThan( 0, $info['days_left'] );
        $this->assertLessThanOrEqual( 14, $info['days_left'] );
    }

    /**
     * Test grace period days calculation
     */
    public function test_grace_period_days_left_calculation(): void {
        global $wp_mock_options;

        // Started 10 days ago - should have ~4 days left
        $wp_mock_options[ TLAT_License_Validator::OPTION_GRACE_START ] = time() - ( 10 * DAY_IN_SECONDS );

        $info = TLAT_License_Validator::get_grace_period_info();

        $this->assertTrue( $info['active'] );
        $this->assertGreaterThanOrEqual( 3, $info['days_left'] );
        $this->assertLessThanOrEqual( 5, $info['days_left'] );
    }

    /**
     * Test is_valid considers grace period
     */
    public function test_is_valid_returns_true_during_grace_period(): void {
        global $wp_mock_options;

        // License is invalid but grace period is active
        $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ] = [
            'status' => 'expired',
            'valid'  => false,
        ];
        $wp_mock_options[ TLAT_License_Validator::OPTION_GRACE_START ] = time();

        // is_valid should return true (grace period active)
        $this->assertTrue( TLAT_License_Validator::is_valid() );

        // is_strictly_valid should return false
        $this->assertFalse( TLAT_License_Validator::is_strictly_valid() );
    }

    /**
     * Test get_status with no status set
     */
    public function test_get_status_returns_default_when_not_set(): void {
        $status = TLAT_License_Validator::get_status();

        $this->assertIsArray( $status );
        $this->assertEquals( 'unknown', $status['status'] );
        $this->assertFalse( $status['valid'] );
    }

    /**
     * Test get_status returns stored status
     */
    public function test_get_status_returns_stored_status(): void {
        global $wp_mock_options;
        $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ] = [
            'status'  => 'valid',
            'message' => 'License activated',
            'valid'   => true,
            'checked' => time(),
        ];

        $status = TLAT_License_Validator::get_status();

        $this->assertEquals( 'valid', $status['status'] );
        $this->assertEquals( 'License activated', $status['message'] );
        $this->assertTrue( $status['valid'] );
    }

    /**
     * Test deactivate clears local options
     */
    public function test_deactivate_clears_local_options(): void {
        global $wp_mock_options;

        // Set up license
        $wp_mock_options[ TLAT_License_Validator::OPTION_KEY ]    = 'TLAT-TEST-1234-5678';
        $wp_mock_options[ TLAT_License_Validator::OPTION_TOKEN ]  = 'test-token-123';
        $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ] = [
            'status' => 'valid',
            'valid'  => true,
        ];

        // Note: deactivate() makes an API call which will fail in tests,
        // but it should still clear local options
        $result = TLAT_License_Validator::deactivate();

        // Verify options were cleared
        $this->assertArrayNotHasKey( TLAT_License_Validator::OPTION_KEY, $wp_mock_options );
        $this->assertArrayNotHasKey( TLAT_License_Validator::OPTION_TOKEN, $wp_mock_options );

        // Status should be set to deactivated
        $this->assertEquals( 'deactivated', $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ]['status'] );
        $this->assertFalse( $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ]['valid'] );
    }

    /**
     * Test deactivate returns error when no license key
     */
    public function test_deactivate_returns_error_when_no_key(): void {
        $result = TLAT_License_Validator::deactivate();

        $this->assertFalse( $result['success'] );
        $this->assertEquals( 'No license key found', $result['message'] );
    }

    /**
     * Test grace period constants
     */
    public function test_grace_period_constants(): void {
        $this->assertEquals( 14, TLAT_License_Validator::GRACE_PERIOD_DAYS );
        $this->assertEquals( 14 * DAY_IN_SECONDS, TLAT_License_Validator::GRACE_PERIOD_SECONDS );
    }

    /**
     * Test validation cache duration
     */
    public function test_validation_cache_duration(): void {
        $this->assertEquals( HOUR_IN_SECONDS, TLAT_License_Validator::VALIDATION_CACHE_DURATION );
    }

    /**
     * Test option key constants
     */
    public function test_option_key_constants(): void {
        $this->assertEquals( 'tlat_license_key', TLAT_License_Validator::OPTION_KEY );
        $this->assertEquals( 'tlat_license_token', TLAT_License_Validator::OPTION_TOKEN );
        $this->assertEquals( 'tlat_license_status', TLAT_License_Validator::OPTION_STATUS );
        $this->assertEquals( 'tlat_license_server_url', TLAT_License_Validator::OPTION_SERVER_URL );
        $this->assertEquals( 'tlat_license_grace_start', TLAT_License_Validator::OPTION_GRACE_START );
    }

    /**
     * Test cron hook constant
     */
    public function test_cron_hook_constant(): void {
        $this->assertEquals( 'tlat_license_heartbeat', TLAT_License_Validator::CRON_HOOK );
    }

    /**
     * Test maybe_validate sets missing status when no key
     */
    public function test_maybe_validate_sets_missing_when_no_key(): void {
        global $wp_mock_options;

        TLAT_License_Validator::maybe_validate();

        $status = $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ];
        $this->assertEquals( 'missing', $status['status'] );
        $this->assertFalse( $status['valid'] );
        $this->assertEquals( 'No license key entered', $status['message'] );
    }

    /**
     * Test maybe_validate skips validation when recently checked
     */
    public function test_maybe_validate_uses_cache(): void {
        global $wp_mock_options;

        // Set up license key and recent status
        $wp_mock_options[ TLAT_License_Validator::OPTION_KEY ]    = 'TLAT-TEST-1234-5678';
        $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ] = [
            'status'  => 'valid',
            'valid'   => true,
            'checked' => time() - 60, // Checked 1 minute ago
        ];

        // This should not make an API call (uses cache)
        TLAT_License_Validator::maybe_validate();

        // Status should remain unchanged
        $this->assertEquals( 'valid', $wp_mock_options[ TLAT_License_Validator::OPTION_STATUS ]['status'] );
    }

    /**
     * Test server URL trailing slash is removed
     */
    public function test_server_url_removes_trailing_slash(): void {
        global $wp_mock_options;

        $wp_mock_options[ TLAT_License_Validator::OPTION_SERVER_URL ] = 'https://license.example.com///';

        $url = TLAT_License_Validator::get_server_url();

        $this->assertEquals( 'https://license.example.com', $url );
    }
}
