<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Blocks;

use StackBoost\ForSupportCandy\Modules\Directory\Shortcodes\DirectoryShortcode;
use StackBoost\ForSupportCandy\Modules\Directory\Data\CustomPostTypes;

/**
 * Directory Block Class.
 */
class DirectoryBlock {

	/**
	 * CustomPostTypes instance.
	 *
	 * @var CustomPostTypes
	 */
	private $cpts;

	/**
	 * Constructor.
	 *
	 * @param CustomPostTypes $cpts An instance of the CustomPostTypes class.
	 */
	public function __construct( CustomPostTypes $cpts ) {
		$this->cpts = $cpts;
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Register the block.
	 */
	public function register_block() {
		// Register Styles for Block Editor & Frontend
		wp_register_style(
			'stackboost-directory-datatables-style',
			'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css',
			array(),
			'1.11.5'
		);
		wp_register_style(
			'stackboost-directory-style',
			\STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-directory.css',
			array( 'stackboost-util-style', 'dashicons' ), // Depend on util style and dashicons
			\STACKBOOST_VERSION
		);

		// Ensure utility styles are available (often shared)
		wp_register_style(
			'stackboost-util-style',
			\STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-util.css',
			array(),
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
		$shortcode = new DirectoryShortcode( $this->cpts );
		return $shortcode->render_directory_shortcode( $attributes );
	}
}
