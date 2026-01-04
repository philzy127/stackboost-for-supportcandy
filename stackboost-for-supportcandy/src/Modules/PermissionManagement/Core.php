<?php

namespace StackBoost\ForSupportCandy\Modules\PermissionManagement;

/**
 * Core Logic for Permission Management.
 * Handles data retrieval, validation, and rule processing.
 *
 * @package StackBoost\ForSupportCandy\Modules\PermissionManagement
 */
class Core {

	/** @var Core|null */
	private static ?Core $instance = null;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): Core {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get available options for a specific SupportCandy field.
	 * Supports standard Dropdowns and other option-based fields.
	 *
	 * @param int $field_id The SupportCandy Field ID.
	 * @return array List of options [ 'id' => int, 'name' => string ].
	 */
	public function get_field_options( int $field_id ): array {
		if ( ! class_exists( '\WPSC_Custom_Field' ) ) {
			return [];
		}

		$field = new \WPSC_Custom_Field( $field_id );
		if ( ! $field->id ) {
			return [];
		}

		// Ensure the field supports options
		// We can check if the type class has 'has_options' property set to true.
		// Or simply check if get_options() returns anything.
		// Based on repo analysis, `WPSC_Custom_Field::get_options()` works for fields with options.

		$options = $field->get_options();
		$result  = [];

		foreach ( $options as $option ) {
			$result[] = [
				'id'   => $option->id,
				'name' => $option->name,
			];
		}

		return $result;
	}

	/**
	 * Get all available roles based on context.
	 *
	 * @param string $context 'wp' for WordPress Roles, 'sc' for SupportCandy Roles.
	 * @return array List of roles [ 'slug' => string, 'name' => string ].
	 */
	public function get_roles( string $context ): array {
		$roles = [];

		if ( 'wp' === $context ) {
			if ( ! function_exists( 'get_editable_roles' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			$wp_roles = get_editable_roles();
			foreach ( $wp_roles as $slug => $details ) {
				$roles[] = [
					'slug' => $slug,
					'name' => $details['name'],
				];
			}
		} elseif ( 'sc' === $context ) {
			// SupportCandy Roles are stored in 'wpsc-agent-roles' option.
			$sc_roles = get_option( 'wpsc-agent-roles', [] );
			foreach ( $sc_roles as $slug => $details ) {
				$roles[] = [
					'slug' => $slug, // The key is the ID/Slug
					'name' => $details['label'], // 'label' holds the display name
				];
			}
		}

		return $roles;
	}

	/**
	 * Retrieve all stored permission rules.
	 *
	 * @return array
	 */
	public function get_rules(): array {
		$options = get_option( 'stackboost_settings', [] );
		return $options['permission_management_rules'] ?? [];
	}

	/**
	 * Save a new rule or update existing ones.
	 * Enforces the "Lite Limit" of 5 rules.
	 *
	 * @param array $new_rules The full array of rules to save.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function save_rules( array $new_rules ) {
		// Check limit
		$limit = 5; // Free/Lite Limit
		// Note: Ideally check for Pro license to bypass limit, but currently spec says "Free users are limited to 5".
		// Assuming strict 5 for now until Pro tier upgrade path is defined for this specific feature limit.
		// Actually, standard pattern is `stackboost_get_license_tier()`.
		// If tier is NOT 'lite', maybe we allow more?
		// The brief says "Free users are limited to 5".
		// Let's enforce 5 for 'lite', unlimited for others.

		$tier = stackboost_get_license_tier();
		if ( 'lite' === $tier && count( $new_rules ) > $limit ) {
			return new \WP_Error( 'limit_exceeded', sprintf( __( 'You have reached the limit of %d rules for the Free version.', 'stackboost-for-supportcandy' ), $limit ) );
		}

		$options = get_option( 'stackboost_settings', [] );
		$options['permission_management_rules'] = $new_rules;

		return update_option( 'stackboost_settings', $options );
	}
}
