<?php

// Mock WordPress functions
define( 'STACKBOOST_PLUGIN_FILE', '/var/www/html/wp-content/plugins/stackboost-for-supportcandy/stackboost-for-supportcandy.php' );

function plugin_basename( $file ) {
    return 'stackboost-for-supportcandy/stackboost-for-supportcandy.php';
}

$mock_options = [];
function get_option( $key, $default = false ) {
    global $mock_options;
    return $mock_options[ $key ] ?? $default;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function wp_enqueue_script() {}
function wp_register_script() {}
function wp_enqueue_style() {}
function wp_localize_script() {}
function wp_create_nonce() {}
function admin_url() {}
function __() {}
function stackboost_is_feature_active() { return false; }
function stackboost_log() {}
function get_current_screen() {}

// Mock dependency classes
namespace StackBoost\ForSupportCandy\WordPress\Admin;
class Settings {
    public static function get_instance() {}
}

namespace StackBoost\ForSupportCandy\Modules\QolEnhancements;
class WordPress {
    public static function get_instance() {}
}

namespace StackBoost\ForSupportCandy\Modules\TicketView;
class WordPress {
    public static function get_instance() {}
}
namespace StackBoost\ForSupportCandy\Modules\AfterHoursNotice;
class WordPress {
    public static function get_instance() {}
}
namespace StackBoost\ForSupportCandy\Modules\DateTimeFormatting;
class WordPress {
    public static function get_instance() {}
}

// Load the class under test
require_once __DIR__ . '/../stackboost-for-supportcandy/src/WordPress/Plugin.php';

use StackBoost\ForSupportCandy\WordPress\Plugin;

// Access private/protected method via reflection or just make it public for test...
// But wait, the method IS public.

$plugin = Plugin::get_instance();

// Test Data
$all_plugins = [
    'stackboost-for-supportcandy/stackboost-for-supportcandy.php' => [
        'Name' => 'StackBoost - For SupportCandy',
    ],
    'other-plugin/other.php' => [
        'Name' => 'Other Plugin',
    ],
];

echo "Testing Dynamic Renaming Logic:\n";

// Case 1: Lite (Default)
global $mock_options;
$mock_options['stackboost_license_tier'] = 'lite';
$result = $plugin->filter_plugin_name( $all_plugins );
if ( $result['stackboost-for-supportcandy/stackboost-for-supportcandy.php']['Name'] === 'StackBoost - For SupportCandy' ) {
    echo "[PASS] Lite Tier: Name remains unchanged.\n";
} else {
    echo "[FAIL] Lite Tier: Name changed to '{$result['stackboost-for-supportcandy/stackboost-for-supportcandy.php']['Name']}'\n";
}

// Case 2: Pro
$mock_options['stackboost_license_tier'] = 'pro';
// Reset name
$all_plugins['stackboost-for-supportcandy/stackboost-for-supportcandy.php']['Name'] = 'StackBoost - For SupportCandy';
$result = $plugin->filter_plugin_name( $all_plugins );
if ( $result['stackboost-for-supportcandy/stackboost-for-supportcandy.php']['Name'] === 'StackBoost - For SupportCandy - Pro' ) {
    echo "[PASS] Pro Tier: Name updated correctly.\n";
} else {
    echo "[FAIL] Pro Tier: Name is '{$result['stackboost-for-supportcandy/stackboost-for-supportcandy.php']['Name']}'\n";
}

// Case 3: Business
$mock_options['stackboost_license_tier'] = 'business';
// Reset name
$all_plugins['stackboost-for-supportcandy/stackboost-for-supportcandy.php']['Name'] = 'StackBoost - For SupportCandy';
$result = $plugin->filter_plugin_name( $all_plugins );
if ( $result['stackboost-for-supportcandy/stackboost-for-supportcandy.php']['Name'] === 'StackBoost - For SupportCandy - Business' ) {
    echo "[PASS] Business Tier: Name updated correctly.\n";
} else {
    echo "[FAIL] Business Tier: Name is '{$result['stackboost-for-supportcandy/stackboost-for-supportcandy.php']['Name']}'\n";
}
