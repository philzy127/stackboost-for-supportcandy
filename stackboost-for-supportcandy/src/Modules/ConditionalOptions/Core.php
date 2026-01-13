<?php

namespace StackBoost\ForSupportCandy\Modules\ConditionalOptions;

use StackBoost\ForSupportCandy\WordPress\Admin\Settings;

/**
 * Core Logic for Conditional Options.
 * Handles data retrieval, validation, and rule processing.
 *
 * @package StackBoost\ForSupportCandy\Modules\ConditionalOptions
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
	 * Supports standard fields (Category, Priority, Status) and generic Custom Fields.
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

		$result = [];
		$raw_options = [];

		// Specific Handling for Standard Fields
		// Note: We check the class type string as specific static properties might be set on subclasses
		if ( 'df_category' === $field->type::$slug ) {
			if ( class_exists( '\WPSC_Category' ) ) {
				$raw_options = \WPSC_Category::find( [ 'items_per_page' => 0 ] )['results'];
			}
		} elseif ( 'df_priority' === $field->type::$slug ) {
			if ( class_exists( '\WPSC_Priority' ) ) {
				$raw_options = \WPSC_Priority::find( [ 'items_per_page' => 0 ] )['results'];
			}
		} elseif ( 'df_status' === $field->type::$slug ) {
			if ( class_exists( '\WPSC_Status' ) ) {
				$raw_options = \WPSC_Status::find( [ 'items_per_page' => 0 ] )['results'];
			}
		} else {
			// Generic Handling for Custom Fields
			if ( $field->type::$has_options ) {
				$raw_options = $field->get_options();
			}
		}

		foreach ( $raw_options as $option ) {
			$id = is_object( $option ) ? $option->id : ( $option['id'] ?? '' );
			$name = is_object( $option ) ? $option->name : ( $option['name'] ?? '' );

			if ( $id ) {
				$result[] = [
					'id'   => $id,
					'name' => $name,
				];
			}
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
			// Add Guest/Visitor for WP Context
			$roles[] = [
				'slug' => 'guest',
				'name' => __( 'Guest / Visitor', 'stackboost-for-supportcandy' ),
			];
		} elseif ( 'sc' === $context ) {
			// SupportCandy Roles are stored in 'wpsc-agent-roles' option.
			$sc_roles = get_option( 'wpsc-agent-roles', [] );
			foreach ( $sc_roles as $slug => $details ) {
				$roles[] = [
					'slug' => $slug, // The key is the ID/Slug
					'name' => $details['label'], // 'label' holds the display name
				];
			}
			// Add Guest/Visitor for SC Context
			$roles[] = [
				'slug' => 'guest',
				'name' => __( 'Guest / Visitor', 'stackboost-for-supportcandy' ),
			];
			// Add User (No SC Role) for SC Context
			$roles[] = [
				'slug' => 'user',
				'name' => __( 'User', 'stackboost-for-supportcandy' ),
			];
		}

		return $roles;
	}

	/**
	 * Check if the feature is enabled globally.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$options = get_option( 'stackboost_settings', [] );
		// Default to false (disabled) if not set, or whatever standard you prefer.
		// Assuming '1' or 'on' means enabled.
		return ! empty( $options['conditional_options_enabled'] );
	}

	/**
	 * Retrieve all stored permission rules.
	 *
	 * @return array
	 */
	public function get_rules(): array {
		$options = get_option( 'stackboost_settings', [] );
		return $options['conditional_options_rules'] ?? [];
	}

	/**
	 * Save configuration (rules + enabled status).
	 * Enforces the "Lite Limit" of 5 rules.
	 *
	 * @param array $new_rules The full array of rules to save.
	 * @param bool  $is_enabled Whether the feature is enabled.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function save_config( array $new_rules, bool $is_enabled ) {
		// Check limit
		$limit = 5; // Free/Lite Limit

		$tier = stackboost_get_license_tier();
		if ( 'lite' === $tier && count( $new_rules ) > $limit ) {
			/* translators: %d: limit number of rules */
			return new \WP_Error( 'limit_exceeded', sprintf( __( 'You have reached the limit of %d rules for the Free version.', 'stackboost-for-supportcandy' ), $limit ) );
		}

		// Construct payload to pass through Settings::sanitize_settings
		$payload = [
			'page_slug'                   => 'stackboost-conditional-options',
			'conditional_options_rules'   => $new_rules,
			'conditional_options_enabled' => $is_enabled ? '1' : '0',
		];

		// We use update_option with the payload.
		// The sanitizer sees 'page_slug', fetches the *existing* settings from DB, merges/updates
		// 'conditional_options_rules' and 'conditional_options_enabled' from our payload.
		return update_option( 'stackboost_settings', $payload );
	}

	/**
	 * Get list of fields that support options (eligible for rules).
	 *
	 * @return array Associative array [ 'slug' => 'Field Name' ]
	 */
	public function get_eligible_fields(): array {
		if ( ! class_exists( '\StackBoost\ForSupportCandy\WordPress\Plugin' ) || ! class_exists( '\WPSC_Custom_Field' ) ) {
			return [];
		}

		$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();
		$all_fields      = $plugin_instance->get_supportcandy_columns();
		$eligible_fields = [];

		foreach ( $all_fields as $slug => $name ) {
			$cf = \WPSC_Custom_Field::get_cf_by_slug( $slug );
			if ( ! $cf ) {
				continue;
			}

			$has_options = false;

			// Check Standard Fields
			if ( in_array( $cf->type::$slug, [ 'df_category', 'df_priority', 'df_status' ], true ) ) {
				$has_options = true;
			}
			// Check Generic Custom Fields
			elseif ( $cf->type::$has_options ) {
				$has_options = true;
			}

			if ( $has_options ) {
				$eligible_fields[ $slug ] = $name;
			}
		}

		return $eligible_fields;
	}
}
