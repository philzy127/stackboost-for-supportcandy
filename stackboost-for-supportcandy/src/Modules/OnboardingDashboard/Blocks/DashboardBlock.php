<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Blocks;

use StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Shortcodes\DashboardShortcode;

/**
 * Onboarding Dashboard Block Class.
 */
class DashboardBlock {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Register the block.
	 */
	public function register_block() {
		wp_register_style(
			'stackboost-onboarding-dashboard',
			\STACKBOOST_PLUGIN_URL . 'assets/css/onboarding-dashboard.css',
			[],
			\STACKBOOST_VERSION
		);

		register_block_type( __DIR__, array(
			'render_callback' => array( $this, 'render_block' ),
		) );
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string Rendered block HTML.
	 */
	public function render_block( $attributes, $content ) {
		return DashboardShortcode::render_shortcode( $attributes );
	}
}
