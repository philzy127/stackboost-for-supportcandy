<?php

namespace StackBoost\ForSupportCandy\Services;

/**
 * Handles communication with the Lemon Squeezy License API.
 *
 * @package StackBoost\ForSupportCandy\Services
 */
class LicenseService {

	/**
	 * API Base URL for Lemon Squeezy.
	 */
	const API_URL = 'https://api.lemonsqueezy.com/v1/licenses';

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key   The license key to activate.
	 * @param string $instance_name A label for this instance (e.g. site URL).
	 * @return array The API response (decoded).
	 */
	public function activate_license( string $license_key, string $instance_name ): array {
		return $this->remote_post( 'activate', [
			'license_key'   => $license_key,
			'instance_name' => $instance_name,
		] );
	}

	/**
	 * Deactivate a license key.
	 *
	 * @param string $license_key The license key to deactivate.
	 * @param string $instance_id The instance ID to deactivate.
	 * @return array The API response (decoded).
	 */
	public function deactivate_license( string $license_key, string $instance_id ): array {
		return $this->remote_post( 'deactivate', [
			'license_key' => $license_key,
			'instance_id' => $instance_id,
		] );
	}

	/**
	 * Validate a license key.
	 *
	 * @param string $license_key The license key to validate.
	 * @param string $instance_id The instance ID (optional).
	 * @return array The API response (decoded).
	 */
	public function validate_license( string $license_key, string $instance_id = '' ): array {
		$body = [ 'license_key' => $license_key ];
		if ( ! empty( $instance_id ) ) {
			$body['instance_id'] = $instance_id;
		}
		return $this->remote_post( 'validate', $body );
	}

	/**
	 * Helper to perform the remote POST request.
	 *
	 * @param string $endpoint The endpoint action (activate, deactivate, validate).
	 * @param array  $body     The body parameters.
	 * @return array Response data or error array.
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

		// Normalize success based on "activated" (activation) or "valid" (validation) or "deactivated" keys.
		// Lemon Squeezy returns { "activated": true, ... } or { "valid": true, ... }
		$success = false;
		if ( isset( $data['activated'] ) && $data['activated'] === true ) {
			$success = true;
		} elseif ( isset( $data['valid'] ) && $data['valid'] === true ) {
			$success = true;
		} elseif ( isset( $data['deactivated'] ) && $data['deactivated'] === true ) {
			$success = true;
		}

		// If the API explicitly returns an error string
		if ( ! empty( $data['error'] ) ) {
			return [
				'success' => false,
				'error'   => $data['error'],
			];
		}

		return array_merge( [ 'success' => $success ], $data );
	}
}
