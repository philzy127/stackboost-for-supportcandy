<?php

namespace StackBoost\ForSupportCandy\Services;

/**
 * Manages license validation, caching, and tier enforcement via Lemon Squeezy.
 *
 * @package StackBoost\ForSupportCandy\Services
 */
class LicenseManager {

	/**
	 * API Base URL for Lemon Squeezy.
	 */
	const API_URL = 'https://api.lemonsqueezy.com/v1/licenses';

    /**
     * Store ID.
     */
    const STORE_ID = 253042;

    /**
     * Variant IDs for Pro Tier.
     */
    const PRO_VARIANTS = [
        1147440, // Pro 1 Site (Annual)
        1147463, // Pro 5 Sites (Annual)
        1147434, // Pro Unlimited (Annual)
        1147435  // Pro Lifetime
    ];

    /**
     * Variant IDs for Business Tier.
     */
    const BIZ_VARIANTS = [
        1147459, // Business 1 Site (Annual)
        1147441, // Business 5 Sites (Annual)
        1147442, // Business Unlimited (Annual)
        1147443  // Business Lifetime
    ];

    /**
     * Activate a license key.
     *
     * @param string $license_key   The license key to activate.
     * @param string $instance_name A label for this instance (e.g. site URL).
     * @return array The API response (decoded) with 'success' boolean.
     */
    public function activate_license( string $license_key, string $instance_name ): array {
        // First, attempt to activate the instance
        $response = $this->remote_post( 'activate', [
            'license_key'   => $license_key,
            'instance_name' => $instance_name,
        ] );

        if ( ! $response['success'] ) {
            return $response;
        }

        // Activation successful. Now we must VALIDATE to get the variant ID and cache it.
        // The activate response contains some info, but validate is the source of truth for checking features.
        // We can use the data from activation for the initial setup.

        $meta = $response['meta'] ?? [];
        $variant_id = $meta['variant_id'] ?? 0;

        // Cache the result immediately
        $this->cache_license_status( $license_key, $variant_id );

        return $response;
    }

    /**
     * Deactivate a license key.
     *
     * @param string $license_key The license key to deactivate.
     * @param string $instance_id The instance ID to deactivate.
     * @return array The API response.
     */
    public function deactivate_license( string $license_key, string $instance_id ): array {
        // Clear cache first
        delete_transient( 'sb_license_status_' . md5( $license_key ) );
        delete_option( 'sb_last_verified_at' );

        return $this->remote_post( 'deactivate', [
            'license_key' => $license_key,
            'instance_id' => $instance_id,
        ] );
    }

    /**
     * Check the status of a license key with caching and grace period.
     *
     * @param string $license_key The license key to check.
     * @return array|bool Returns cached data array if valid, or false if invalid/expired.
     */
    public function check_license_status( string $license_key ) {
        if ( empty( $license_key ) ) {
            return false;
        }

        $transient_key = 'sb_license_status_' . md5( $license_key );
        $cached_data = get_transient( $transient_key );

        // 1. Check Cache
        if ( $cached_data !== false ) {
            return $cached_data; // Return the cached array (variant_id, etc.)
        }

        // 2. Check Remote
        $response = $this->remote_post( 'validate', [ 'license_key' => $license_key ] );

        if ( $response['success'] ) {
            // Valid! Cache it.
            $variant_id = $response['meta']['variant_id'] ?? 0;
            $data = $this->cache_license_status( $license_key, $variant_id );
            return $data;
        }

        // 3. Grace Period (Fail-Safe)
        // If the API call failed due to network error (not just "invalid key")
        // Note: Our simple remote_post wrapper currently returns success=false for both API errors and network errors.
        // For a robust implementation, we should distinguish them.
        // For now, if the error is explicitly "Invalid license key", we fail.
        // If it's something else (timeout, or generic), we check grace period.

        $error_msg = $response['error'] ?? '';
        $is_definitive_failure = ( stripos( $error_msg, 'not found' ) !== false || stripos( $error_msg, 'invalid' ) !== false );

        if ( ! $is_definitive_failure ) {
            $last_verified = get_option( 'sb_last_verified_at', 0 );
            // 72 Hours = 3 Days
            if ( ( time() - $last_verified ) < ( 72 * HOUR_IN_SECONDS ) ) {
                // Inside grace period. Return the LAST KNOWN variant ID if we can find it?
                // Problem: We don't have the last known variant ID if the transient is gone.
                // Solution: We should store the variant_id in a persistent option as backup.
                $backup_variant = get_option( 'stackboost_license_variant_id', 0 );
                if ( $backup_variant ) {
                    return [ 'valid' => true, 'variant_id' => $backup_variant, 'grace_period' => true ];
                }
            }
        }

        return false;
    }

    /**
     * Helper to cache the license status.
     */
    private function cache_license_status( $license_key, $variant_id ) {
        $data = [
            'valid' => true,
            'variant_id' => $variant_id
        ];
        // Cache for 12 hours
        set_transient( 'sb_license_status_' . md5( $license_key ), $data, 12 * HOUR_IN_SECONDS );

        // Update persistent timestamp for grace period
        update_option( 'sb_last_verified_at', time() );

        // Update persistent backup for variant ID (for grace period recovery)
        update_option( 'stackboost_license_variant_id', $variant_id );

        return $data;
    }

    /**
     * Determine the tier based on a variant ID.
     *
     * @param int $variant_id
     * @return string 'lite', 'pro', or 'business'
     */
    public function get_tier_from_variant( int $variant_id ): string {
        if ( in_array( $variant_id, self::BIZ_VARIANTS, true ) ) {
            return 'business';
        }
        if ( in_array( $variant_id, self::PRO_VARIANTS, true ) ) {
            return 'pro';
        }
        return 'lite';
    }

	/**
	 * Helper to perform the remote POST request.
	 */
	private function remote_post( string $endpoint, array $body ): array {
		$url = self::API_URL . '/' . $endpoint;

		$response = wp_remote_post( $url, [
			'timeout' => 15,
			'headers' => [
				'Accept' => 'application/json',
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( empty( $data ) ) {
			return [
				'success' => false,
				'error'   => 'Empty response from licensing server.',
			];
		}

		$success = false;
		if ( isset( $data['activated'] ) && $data['activated'] === true ) {
			$success = true;
		} elseif ( isset( $data['valid'] ) && $data['valid'] === true ) {
			$success = true;
		} elseif ( isset( $data['deactivated'] ) && $data['deactivated'] === true ) {
			$success = true;
		}

		if ( ! empty( $data['error'] ) ) {
			return [
				'success' => false,
				'error'   => $data['error'],
			];
		}

		return array_merge( [ 'success' => $success ], $data );
	}
}
