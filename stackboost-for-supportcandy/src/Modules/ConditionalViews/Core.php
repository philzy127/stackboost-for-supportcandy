<?php

namespace StackBoost\ForSupportCandy\Modules\ConditionalViews;

/**
 * Core business logic for the Conditional Views feature.
 *
 * This class processes the rules created by the user to determine which
 * columns should be visible in a given context. It is decoupled from WordPress.
 *
 * @package StackBoost\ForSupportCandy\Modules\ConditionalViews
 */
class Core {

	/**
	 * Processes a set of rules against a current view ID to determine UI changes.
	 *
	 * While the actual showing/hiding is done in JavaScript, this class
	 * could be used for server-side validation or pre-processing if needed.
	 * The primary role of this class in the current architecture is to provide
	 * a clean, validated, and structured set of rules for the frontend.
	 *
	 * @param array|null $rules An array of rule objects.
	 * @return array The validated and structured rules, ready for use.
	 */
	public function get_processed_rules( ?array $rules ): array {
		if ( ! is_array( $rules ) ) {
			return [];
		}

		$sanitized_rules = [];
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty($rule['columns'])) {
				continue;
			}

			$sanitized_rule = [];
			$sanitized_rule['action']    = isset( $rule['action'] ) && in_array( $rule['action'], [ 'show', 'hide', 'show_only' ], true ) ? $rule['action'] : 'hide';
			$sanitized_rule['condition'] = isset( $rule['condition'] ) && in_array( $rule['condition'], [ 'in_view', 'not_in_view' ], true ) ? $rule['condition'] : 'in_view';
			$sanitized_rule['view']      = isset( $rule['view'] ) ? (string) $rule['view'] : '0';
			$sanitized_rule['columns']   = (string) $rule['columns']; // The column slug
			$sanitized_rules[]         = $sanitized_rule;
		}

		return $sanitized_rules;
	}
}