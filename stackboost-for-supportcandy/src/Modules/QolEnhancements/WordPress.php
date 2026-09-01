<?php

namespace StackBoost\ForSupportCandy\Modules\QolEnhancements;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Modules\QolEnhancements\Core as QolCore;
use StackBoost\ForSupportCandy\WordPress\Plugin;

/**
 * WordPress Adapter for Quality of Life Enhancements.
 *
 * @package StackBoost\ForSupportCandy\Modules\QolEnhancements
 */
class WordPress extends Module {

	/** @var WordPress|null */
	private static ?WordPress $instance = null;

	/** @var QolCore */
	private QolCore $core;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): WordPress {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! stackboost_is_feature_active( 'qol_enhancements' ) ) {
			return;
		}

		$this->core = new QolCore();
		parent::__construct();
	}

	/**
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'qol_enhancements';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		$options = get_option( 'stackboost_settings', [] );
		if ( ! empty( $options['enable_clean_excessive_breaks'] ) ) {
			// 1. Clean PHPMailer payload directly before email delivery
			add_action( 'phpmailer_init', [ $this, 'clean_phpmailer_body' ], 9999 );

			// 2. Intercept SupportCandy-specific email/macro filters
			add_filter( 'wpsc_ticket_macro_value', [ $this, 'clean_macro_filter' ], 9999, 3 );
			add_filter( 'wpsc_email_notification_body', [ $this, 'clean_string_filter' ], 9999, 1 );
			add_filter( 'wpsc_email_body', [ $this, 'clean_string_filter' ], 9999, 1 );

			// 3. Output Buffer fallback for page renders / AJAX calls
			add_action( 'wp_loaded', [ $this, 'start_global_buffer' ] );
		}
	}

	/**
	 * Clean PHPMailer payload directly before email delivery.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
	 */
	public function clean_phpmailer_body( $phpmailer ) {
		if ( ! empty( $phpmailer->Body ) ) {
			$phpmailer->Body = $this->core->strip_excessive_breaks( $phpmailer->Body );
		}
		if ( ! empty( $phpmailer->AltBody ) ) {
			$phpmailer->AltBody = $this->core->strip_excessive_breaks( $phpmailer->AltBody );
		}
	}

	/**
	 * Intercept wpsc_ticket_macro_value filter.
	 *
	 * @param string $value
	 * @param string $macro
	 * @param mixed $ticket
	 * @return string
	 */
	public function clean_macro_filter( $value, $macro, $ticket ) {
		return $this->core->strip_excessive_breaks( $value );
	}

	/**
	 * Intercept email body filters.
	 *
	 * @param string $content
	 * @return string
	 */
	public function clean_string_filter( $content ) {
		return $this->core->strip_excessive_breaks( $content );
	}

	/**
	 * Output Buffer fallback for page renders / AJAX calls.
	 */
	public function start_global_buffer() {
		ob_start( [ $this->core, 'strip_excessive_breaks' ] );
	}
}
