<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard;

class OnboardingDashboard {

	/**
	 * The singleton instance.
	 *
	 * @var OnboardingDashboard|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return OnboardingDashboard
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the module.
	 */
	public function init() {
		stackboost_log( 'OnboardingDashboard Module Initialized.', 'onboarding' );

		// Load Custom Post Types
		Data\CustomPostTypes::init();

		// Load Admin Pages
		if ( is_admin() ) {
			Admin\Page::init();
			Admin\Sequence::init();
			Admin\Staff::init();
			Admin\ImportExport::init();
			Admin\Settings::init();
		}

		// Load Shortcodes
		Shortcodes\DashboardShortcode::init();

		// Load AJAX Handlers
		Ajax\CertificateHandler::init();
	}
}
