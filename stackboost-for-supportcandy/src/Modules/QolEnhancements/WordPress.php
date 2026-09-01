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
	 * Helper to check if clean line breaks option is enabled in settings.
	 *
	 * @return bool
	 */
	private function is_clean_breaks_enabled(): bool {
		$options = get_option( 'stackboost_settings', [] );
		return ! empty( $options['enable_clean_excessive_breaks'] );
	}

	/**
	 * Helper to check if clean HR tags option is enabled in settings.
	 *
	 * @return bool
	 */
	private function is_clean_hrs_enabled(): bool {
		$options = get_option( 'stackboost_settings', [] );
		return ! empty( $options['enable_clean_excessive_hrs'] );
	}

	/**
	 * Helper to process string through active cleanup routines.
	 *
	 * @param mixed $content
	 * @return mixed
	 */
	public function process_cleanup( $content ) {
		if ( $this->is_clean_breaks_enabled() ) {
			$content = $this->core->strip_excessive_breaks( $content );
		}
		if ( $this->is_clean_hrs_enabled() ) {
			$content = $this->core->strip_excessive_hrs( $content );
		}
		return $content;
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		// 1. Clean PHPMailer payload directly before email delivery
		add_action( 'phpmailer_init', [ $this, 'clean_phpmailer_body' ], 9999 );

		// 2. Intercept SupportCandy-specific email/macro filters & email payload data
		add_filter( 'wpsc_ticket_macro_value', [ $this, 'clean_macro_filter' ], 9999, 3 );
		add_filter( 'wpsc_email_notification_body', [ $this, 'clean_string_filter' ], 9999, 1 );
		add_filter( 'wpsc_email_body', [ $this, 'clean_string_filter' ], 9999, 1 );
		add_filter( 'wpsc_create_ticket_email_data', [ $this, 'clean_email_data_filter' ], 9999, 2 );
		add_filter( 'wpsc_agent_reply_email_data', [ $this, 'clean_email_data_filter' ], 9999, 2 );
		add_filter( 'wpsc_cust_reply_email_data', [ $this, 'clean_email_data_filter' ], 9999, 2 );

		// 3. Output Buffer fallback for page renders / AJAX calls
		add_action( 'wp_loaded', [ $this, 'start_global_buffer' ] );
	}

	/**
	 * Clean PHPMailer payload directly before email delivery.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
	 */
	public function clean_phpmailer_body( $phpmailer ) {
		if ( ! empty( $phpmailer->Body ) ) {
			$phpmailer->Body = $this->process_cleanup( $phpmailer->Body );
		}
		if ( ! empty( $phpmailer->AltBody ) ) {
			$phpmailer->AltBody = $this->process_cleanup( $phpmailer->AltBody );
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
	public function clean_macro_filter( $value, $macro = '', $ticket = null ) {
		return $this->process_cleanup( $value );
	}

	/**
	 * Intercept email body filters.
	 *
	 * @param string $content
	 * @return string
	 */
	public function clean_string_filter( $content ) {
		return $this->process_cleanup( $content );
	}

	/**
	 * Intercept SupportCandy email data arrays (body, subject, etc.).
	 *
	 * @param array $email_data
	 * @param mixed $ticket
	 * @return array
	 */
	public function clean_email_data_filter( $email_data, $ticket = null ) {
		if ( is_array( $email_data ) ) {
			if ( ! empty( $email_data['body'] ) ) {
				$email_data['body'] = $this->process_cleanup( $email_data['body'] );
			}
			if ( ! empty( $email_data['message'] ) ) {
				$email_data['message'] = $this->process_cleanup( $email_data['message'] );
			}
		}
		return $email_data;
	}

	/**
	 * Output Buffer fallback for page renders / AJAX calls.
	 */
	public function start_global_buffer() {
		$clean_breaks = $this->is_clean_breaks_enabled();
		$clean_hrs    = $this->is_clean_hrs_enabled();

		if ( ! $clean_breaks && ! $clean_hrs ) {
			return;
		}

		$core = $this->core;
		ob_start( function( $html ) use ( $core, $clean_breaks, $clean_hrs ) {
			if ( $clean_breaks ) {
				$html = $core->strip_excessive_breaks( $html );
			}
			if ( $clean_hrs ) {
				$html = $core->strip_excessive_hrs( $html );
			}
			return $html;
		} );
	}
}
