<?php

namespace StackBoost\ForSupportCandy\Modules\TicketGuard;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core Business Logic for Ticket Guard.
 *
 * Handles rules management, evaluation, and backend validation.
 *
 * @package StackBoost\ForSupportCandy\Modules\TicketGuard
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
	 * Check if the Ticket Guard feature is enabled globally.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( ! stackboost_is_feature_active( 'ticket_guard' ) ) {
			return false;
		}

		$options = get_option( 'stackboost_settings', [] );
		return ! empty( $options['enable_ticket_guard'] );
	}

	/**
	 * Retrieve all active Ticket Guard rules.
	 *
	 * @return array
	 */
	public function get_rules(): array {
		$options = get_option( 'stackboost_settings', [] );
		$rules   = $options['ticket_guard_rules'] ?? [];
		return is_array( $rules ) ? $rules : [];
	}

	/**
	 * Save Ticket Guard configuration rules and master switch state.
	 *
	 * @param array $rules
	 * @param bool  $enabled
	 * @return true|\WP_Error
	 */
	public function save_config( array $rules, bool $enabled ) {
		$options = get_option( 'stackboost_settings', [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$sanitized_rules = [];
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$keywords_raw = $rule['keywords'] ?? [];
			if ( is_string( $keywords_raw ) ) {
				$keywords_array = array_filter( array_map( 'trim', explode( "\n", $keywords_raw ) ) );
			} else {
				$keywords_array = array_map( 'sanitize_text_field', (array) $keywords_raw );
			}

			$sanitized_rules[] = [
				'id'                 => sanitize_key( $rule['id'] ?? ( 'rule_' . wp_rand( 1000, 9999 ) ) ),
				'name'               => sanitize_text_field( $rule['name'] ?? '' ),
				'enabled'            => ! empty( $rule['enabled'] ),
				'monitored_fields'   => array_map( 'sanitize_key', (array) ( $rule['monitored_fields'] ?? [] ) ),
				'keywords'           => array_values( $keywords_array ),
				'trigger_category'   => sanitize_text_field( $rule['trigger_category'] ?? '' ),
				'suggested_category' => sanitize_text_field( $rule['suggested_category'] ?? '' ),
				'actions'            => [
					'disable_submit'      => ! empty( $rule['actions']['disable_submit'] ),
					'show_modal'          => ! empty( $rule['actions']['show_modal'] ),
					'show_inline_warning' => ! empty( $rule['actions']['show_inline_warning'] ),
					'auto_swap_category'  => ! empty( $rule['actions']['auto_swap_category'] ),
				],
				'messaging'          => [
					'modal_title'    => sanitize_text_field( $rule['messaging']['modal_title'] ?? '' ),
					'modal_body'     => wp_kses_post( $rule['messaging']['modal_body'] ?? '' ),
					'inline_warning' => sanitize_text_field( $rule['messaging']['inline_warning'] ?? '' ),
				],
			];
		}

		$options['enable_ticket_guard'] = $enabled ? 1 : 0;
		$options['ticket_guard_rules']  = $sanitized_rules;

		$updated = update_option( 'stackboost_settings', $options );

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( sprintf( 'Ticket Guard settings saved. Enabled: %d, Rule Count: %d', $enabled ? 1 : 0, count( $sanitized_rules ) ), 'ticket_guard' );
		}

		return true;
	}

	/**
	 * Retrieve SupportCandy field choices and categories for the admin rule builder.
	 *
	 * @return array
	 */
	public function get_form_options(): array {
		$fields = [];
		if ( class_exists( '\StackBoost\ForSupportCandy\WordPress\Plugin' ) ) {
			$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();
			$fields          = $plugin_instance->get_supportcandy_columns();
		}

		$categories = [];
		if ( class_exists( '\WPSC_Category' ) ) {
			$raw_categories = \WPSC_Category::find( [ 'items_per_page' => 0 ] )['results'] ?? [];
			foreach ( $raw_categories as $cat ) {
				$categories[] = [
					'id'   => is_object( $cat ) ? $cat->id : $cat['id'],
					'name' => is_object( $cat ) ? $cat->name : $cat['name'],
				];
			}
		}

		return [
			'fields'     => $fields,
			'categories' => $categories,
		];
	}

	/**
	 * Evaluate incoming ticket creation data against configured rules for backend enforcement.
	 *
	 * @param array $ticket_data Incoming data filter array from `wpsc_create_ticket_data`.
	 * @return array Sanitized/filtered ticket data.
	 */
	public function evaluate_submission_rules( array $ticket_data ): array {
		if ( ! $this->is_enabled() ) {
			return $ticket_data;
		}

		$rules = $this->get_rules();
		if ( empty( $rules ) ) {
			return $ticket_data;
		}

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$monitored_fields  = (array) ( $rule['monitored_fields'] ?? [] );
			$keywords          = (array) ( $rule['keywords'] ?? [] );
			$trigger_category  = $rule['trigger_category'] ?? '';
			$actions           = $rule['actions'] ?? [];

			if ( empty( $monitored_fields ) || empty( $keywords ) ) {
				continue;
			}

			// Check category match if configured
			if ( ! empty( $trigger_category ) && isset( $ticket_data['df_category'] ) ) {
				if ( (string) $ticket_data['df_category'] !== (string) $trigger_category ) {
					continue;
				}
			}

			// Scan fields for keywords
			$matched = false;
			foreach ( $monitored_fields as $field_slug ) {
				if ( empty( $ticket_data[ $field_slug ] ) ) {
					continue;
				}

				$content = (string) $ticket_data[ $field_slug ];
				foreach ( $keywords as $kw ) {
					if ( empty( trim( $kw ) ) ) {
						continue;
					}

					if ( false !== stripos( $content, trim( $kw ) ) ) {
						$matched = true;
						break 2;
					}
				}
			}

			if ( $matched ) {
				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( sprintf( 'Ticket Guard Rule Matched on Backend: %s', $rule['name'] ?? 'Unnamed' ), 'ticket_guard' );
				}

				// If auto-swap category action is set, apply swap on backend
				if ( ! empty( $actions['auto_swap_category'] ) && ! empty( $rule['suggested_category'] ) ) {
					$ticket_data['df_category'] = $rule['suggested_category'];
				}
			}
		}

		return $ticket_data;
	}
}
