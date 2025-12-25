<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey\Blocks;

use StackBoost\ForSupportCandy\Modules\AfterTicketSurvey\Shortcode;

/**
 * After Ticket Survey Block Class.
 */
class SurveyBlock {

	/** @var Shortcode */
	private Shortcode $shortcode;

	/**
	 * Constructor.
	 *
	 * @param Shortcode $shortcode Instance of the Shortcode class.
	 */
	public function __construct( Shortcode $shortcode ) {
		$this->shortcode = $shortcode;
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Register the block.
	 */
	public function register_block() {
		wp_register_style(
			'stackboost-ats-frontend',
			\STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-ats-frontend.css',
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
		return $this->shortcode->render_shortcode();
	}
}
