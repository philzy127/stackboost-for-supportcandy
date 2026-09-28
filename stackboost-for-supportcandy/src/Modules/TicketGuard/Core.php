<?php

namespace StackBoost\ForSupportCandy\Modules\TicketGuard;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Core\Singleton;

/**
 * Core Business Logic for Ticket Guard.
 *
 * Handles rules management, evaluation, and backend validation.
 *
 * @package StackBoost\ForSupportCandy\Modules\TicketGuard
 */
class Core extends Singleton {

	/** @var Core|null */
	protected static ?Singleton $instance = null;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): Core {
		return parent::get_instance();
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
