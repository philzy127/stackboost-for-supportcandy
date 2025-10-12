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
	 * CustomPostTypes instance.
	 *
	 * @var CustomPostTypes
	 */
	public $cpts;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cpts = new CustomPostTypes();
		new MetaBoxes( $this->cpts );
		new DirectoryShortcode( $this->cpts );
		new Importer( $this->cpts );
		new Clearer( $this->cpts );
	}
}