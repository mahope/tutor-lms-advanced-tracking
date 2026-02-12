<?php
/**
 * Integration tests for license activation/deactivation flow
 *
 * These tests require a running license server to test against.
 * Set TLAT_TEST_SERVER_URL environment variable to run integration tests.
 *
 * @package TutorLMSAdvancedTracking
 */

namespace TLAT\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TLAT_License_Validator;

/**
 * @group integration
 */
class LicenseFlowTest extends TestCase {

    /**
     * License server URL for tests
     */
    protected static string $serverUrl;

    /**
     * Test license key (must be valid on test server)
     */
    protected static string $testLicenseKey;

    /**
     * Skip integration tests if server URL not configured
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        self::$serverUrl = getenv( 'TLAT_TEST_SERVER_URL' ) ?: '';
        self::$testLicenseKey = getenv( 'TLAT_TEST_LICENSE_KEY' ) ?: '';

        if ( empty( self::$serverUrl ) ) {
            self::markTestSkipped(
                'Integration tests require TLAT_TEST_SERVER_URL environment variable.'
            );
        }
    }

    /**
     * Reset options before each test
     */
    protected function setUp(): void {
        parent::setUp();
        global $wp_mock_options;
        $wp_mock_options = [];

        // Set test server URL
        if ( ! empty( self::$serverUrl ) ) {
            $wp_mock_options[ TLAT_License_Validator::OPTION_SERVER_URL ] = self::$serverUrl;
        }
    }

    /**
     * Test full activation → deactivation → reactivation flow
     *
     * @requires extension curl
     */
    public function test_full_license_flow(): void {
        if ( empty( self::$testLicenseKey ) ) {
            $this->markTestSkipped( 'TLAT_TEST_LICENSE_KEY not set.' );
        }

        // Step 1: Activate
        $activateResult = TLAT_License_Validator::activate( self::$testLicenseKey );
        $this->assertTrue(
            $activateResult['success'],
            'Activation failed: ' . ( $activateResult['message'] ?? 'Unknown error' )
        );
        $this->assertEquals( 'valid', $activateResult['status'] );

        // Verify is_valid returns true
        $this->assertTrue( TLAT_License_Validator::is_valid() );
        $this->assertTrue( TLAT_License_Validator::is_strictly_valid() );

        // Step 2: Validate
        $validateResult = TLAT_License_Validator::validate();
        $this->assertTrue( $validateResult['valid'], 'Validation failed after activation' );

        // Step 3: Deactivate
        $deactivateResult = TLAT_License_Validator::deactivate();
        $this->assertTrue(
            $deactivateResult['success'],
            'Deactivation failed: ' . ( $deactivateResult['message'] ?? 'Unknown error' )
        );

        // Verify is_valid returns false after deactivation
        $this->assertFalse( TLAT_License_Validator::is_strictly_valid() );

        // Step 4: Reactivate
        $reactivateResult = TLAT_License_Validator::activate( self::$testLicenseKey );
        $this->assertTrue(
            $reactivateResult['success'],
            'Reactivation failed: ' . ( $reactivateResult['message'] ?? 'Unknown error' )
        );

        // Cleanup: deactivate at end
        TLAT_License_Validator::deactivate();
    }

    /**
     * Test activation with invalid key
     */
    public function test_activation_with_invalid_key(): void {
        $result = TLAT_License_Validator::activate( 'TLAT-INVALID-0000-0000-0000' );

        $this->assertFalse( $result['success'] );
        $this->assertNotEquals( 'valid', $result['status'] );
    }

    /**
     * Test heartbeat sends correctly
     */
    public function test_heartbeat_with_valid_license(): void {
        if ( empty( self::$testLicenseKey ) ) {
            $this->markTestSkipped( 'TLAT_TEST_LICENSE_KEY not set.' );
        }

        // Activate first
        TLAT_License_Validator::activate( self::$testLicenseKey );

        // Send heartbeat (should not throw)
        TLAT_License_Validator::send_heartbeat();

        // Verify license still valid
        $this->assertTrue( TLAT_License_Validator::is_valid() );

        // Cleanup
        TLAT_License_Validator::deactivate();
    }
}
