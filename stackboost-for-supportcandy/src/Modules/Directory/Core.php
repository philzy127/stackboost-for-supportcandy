<?php
/**
 * Core functionality for the Directory module.
 *
 * @package StackBoost
 * @subpackage Modules\Directory
 */

namespace StackBoost\ForSupportCandy\Modules\Directory;

use StackBoost\ForSupportCandy\Modules\Directory\Data\CustomPostTypes;
use StackBoost\ForSupportCandy\Modules\Directory\Data\MetaBoxes;
use StackBoost\ForSupportCandy\Modules\Directory\Shortcodes\DirectoryShortcode;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\Importer;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\Clearer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Core class for the Directory module.
 */
class Core {
	/**
	 * The single instance of the class.
	 *
	 * @var Core
	 */
	protected static $instance = null;

	/**
	 * Main Core instance.
	 *
	 * Ensures only one instance of Core is loaded or can be loaded.
	 *
	 * @return Core - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		$cpts = new CustomPostTypes();
		new MetaBoxes( $cpts );
		new DirectoryShortcode( $cpts );
		new Importer( $cpts );
		new Clearer( $cpts );

		new WordPress( $cpts );
	}
}