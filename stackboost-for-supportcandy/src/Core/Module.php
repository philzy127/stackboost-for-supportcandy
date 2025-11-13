<?php

namespace StackBoost\ForSupportCandy\Core;

/**
 * Abstract base class for all modules.
 *
 * Provides a common structure and shared functionality for WordPress adapters.
 *
 * @package StackBoost\ForSupportCandy\Core
 */
abstract class Module {

	/**
	 * Constructor.
	 *
	 * Registers the init_hooks method to be called.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Get the unique slug for the module.
	 *
	 * @return string
	 */
	abstract public function get_slug(): string;

	/**
	 * Initialize WordPress hooks for the module.
	 */
	abstract public function init_hooks();

	/**
	 * Render a checkbox field for a settings page.
	 *
	 * @param array $args The arguments for the field.
	 */
	public function render_checkbox_field( array $args ) {
		$options = get_option( 'stackboost_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? 1 : 0;
		echo '<input type="hidden" name="stackboost_settings[' . esc_attr( $args['id'] ) . ']" value="0">';
		echo '<input type="checkbox" id="' . esc_attr( $args['id'] ) . '" name="stackboost_settings[' . esc_attr( $args['id'] ) . ']" value="1" ' . checked( 1, $value, false ) . '>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Render a number field for a settings page.
	 *
	 * @param array $args The arguments for the field.
	 */
	public function render_number_field( array $args ) {
		$options = get_option( 'stackboost_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : ( $args['default'] ?? '' );
		echo '<input type="number" id="' . esc_attr( $args['id'] ) . '" name="stackboost_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '" class="small-text">';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Render a textarea field for a settings page.
	 *
	 * @param array $args The arguments for the field.
	 */
	public function render_textarea_field( array $args ) {
		$options = get_option( 'stackboost_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
		$class   = ! empty( $args['class'] ) ? esc_attr( $args['class'] ) : 'large-text';
		echo '<textarea id="' . esc_attr( $args['id'] ) . '" name="stackboost_settings[' . esc_attr( $args['id'] ) . ']" rows="5" class="' . $class . '">' . esc_textarea( $value ) . '</textarea>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Render a WP Editor (WYSIWYG) field.
	 *
	 * @param array $args The arguments for the field.
	 */
	public function render_wp_editor_field( array $args ) {
		$options = get_option( 'stackboost_settings', [] );
		$default_content = '<strong>StackBoost Helpdesk -- After Hours</strong><br><br>You have submitted an IT ticket outside of normal business hours, and it will be handled in the order it was received. If this is an emergency, or has caused a complete stoppage of work, please call the IT On-Call number at: <u>(202) 996-8415</u> <br><br> (Available <b>5pm</b> to <b>11pm(EST) M-F, 8am to 11pm</b> weekends and Holidays)';
		$content = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : $default_content;
		wp_editor(
			$content,
			'stackboost_settings_' . esc_attr( $args['id'] ),
			[
				'textarea_name' => 'stackboost_settings[' . esc_attr( $args['id'] ) . ']',
				'media_buttons' => false,
				'textarea_rows' => 10,
				'teeny'         => true,
			]
		);
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

    /**
	 * Render a select dropdown field.
	 */
	public function render_select_field( $args ) {
		$options = get_option( 'stackboost_settings', [] );
		$value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : '';
		$class   = ! empty( $args['class'] ) ? esc_attr( $args['class'] ) : 'regular';
		$choices = ! empty( $args['choices'] ) && is_array( $args['choices'] ) ? $args['choices'] : [];

		echo '<select id="' . esc_attr( $args['id'] ) . '" name="stackboost_settings[' . esc_attr( $args['id'] ) . ']" class="' . $class . '">';

		if ( isset( $args['placeholder'] ) ) {
			echo '<option value="">' . esc_html( $args['placeholder'] ) . '</option>';
		}

		foreach ( $choices as $choice_val => $choice_label ) {
			echo '<option value="' . esc_attr( $choice_val ) . '" ' . selected( $value, $choice_val, false ) . '>' . esc_html( $choice_label ) . '</option>';
		}
		echo '</select>';

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}
}