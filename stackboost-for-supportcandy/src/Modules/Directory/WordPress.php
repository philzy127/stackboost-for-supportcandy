<?php

namespace StackBoost\ForSupportCandy\Modules\Directory;

/**
 * WordPress integration class for the Directory service.
 *
 * This class acts as the bridge between the Directory's core logic and the
 * broader WordPress environment. It ensures the Core service is instantiated
 * and accessible to other parts of the StackBoost plugin.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory
 */
final class WordPress {

    /**
     * The single instance of the class.
     *
     * @var WordPress|null
     */
    private static ?WordPress $instance = null;

    /**
     * The Core service instance.
     *
     * @var Core
     */
    public Core $core;

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
     * Private constructor to prevent direct instantiation.
     * It initializes the Core service.
     */
    private function __construct() {
        $this->core = Core::get_instance();
    }
}