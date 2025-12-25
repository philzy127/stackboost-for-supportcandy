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
