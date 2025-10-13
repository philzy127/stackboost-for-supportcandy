<?php
/**
 * WordPress integration for the Directory Migration module.
 *
 * @package StackBoost
 */

namespace StackBoost\ForSupportCandy\Modules\DirectoryMigration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress Integration Class
 */
final class WordPress {

	/**
	 * The single instance of the class.
	 *
	 * @var WordPress|null
	 */
	private static ?WordPress $instance = null;

	/**
	 * Get the single instance of the class.
	 *
	 * @return WordPress
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
	private function __construct() {
		new Core();
	}
}