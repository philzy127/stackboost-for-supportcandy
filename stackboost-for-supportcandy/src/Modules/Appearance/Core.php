<?php

namespace StackBoost\ForSupportCandy\Modules\Appearance;

use StackBoost\ForSupportCandy\Modules\Appearance\WordPress;

/**
 * Appearance Module Entry Point.
 */
class Core {

    /** @var Core|null */
    private static ?Core $instance = null;

    /** @var WordPress */
    private $wordpress;

    /**
     * Get the single instance of the class.
     */
    public static function get_instance(): Core {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->wordpress = new WordPress();
        $this->wordpress->init();
    }
}
