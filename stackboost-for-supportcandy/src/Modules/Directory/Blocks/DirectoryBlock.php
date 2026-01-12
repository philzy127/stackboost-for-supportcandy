<?php


namespace StackBoost\ForSupportCandy\Modules\Directory\Blocks;

if ( ! defined( 'ABSPATH' ) ) exit;

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
			'stackboost-directory-style',
			\STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-directory.css',
			array( 'stackboost-util', 'dashicons', 'stackboost-datatables-css' ), // Depend on util style, dashicons, and central datatables
			\STACKBOOST_VERSION
		);

		// Ensure utility styles are available (often shared)
		// Now registered centrally in Plugin.php as 'stackboost-util'

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