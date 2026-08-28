<?php
/**
 * Test UTM Response Placement functionality and sanitization.
 */

namespace WPSC {
	class FieldMock {
		public static $slug = 'cf_textfield';
	}

	class FieldTypeObject {
		public $type = 'WPSC\FieldMock';
	}
}

namespace {
	// Global mocks
	$mock_options = [];

	function get_option( $key, $default = false ) {
		global $mock_options;
		return $mock_options[ $key ] ?? $default;
	}

	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
	function esc_html_e( $text, $domain = 'default' ) { echo esc_html( $text ); }
	function esc_attr_e( $text, $domain = 'default' ) { echo esc_attr( $text ); }
	function __( $text, $domain = 'default' ) { return $text; }
	function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key ) ); }
	function sanitize_text_field( $str ) { return trim( (string) $str ); }
	function wp_unslash( $val ) { return $val; }
	function apply_filters( $tag, $value, ...$args ) { return $value; }
	function stackboost_log( $msg, $context = '' ) {}
	function wp_timezone() { return new DateTimeZone( 'UTC' ); }

	// Mock SupportCandy classes
	class WPSC_Custom_Field {
		public static $custom_fields = [];
	}

	class WPSC_Ticket {
		public $id = 101;
		public $subject = 'Help with billing';
		public $category = 'Billing';

		public function get_description_thread() {
			return false;
		}
	}

	class StackBoost_Plugin_Mock {
		private static $instance = null;
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}
		public function get_supportcandy_columns() {
			return [
				'subject'  => 'Subject',
				'category' => 'Category',
			];
		}
	}
}

namespace StackBoost\ForSupportCandy\WordPress {
	class Plugin {
		public static function get_instance() {
			return \StackBoost_Plugin_Mock::get_instance();
		}
	}
}

namespace {
	// Define constants needed by Settings
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	if ( ! defined( 'STACKBOOST_CAP_MANAGE_SETTINGS' ) ) {
		define( 'STACKBOOST_CAP_MANAGE_SETTINGS', 'manage_options' );
	}
	if ( ! defined( 'STACKBOOST_CAP_MANAGE_UTM' ) ) {
		define( 'STACKBOOST_CAP_MANAGE_UTM', 'manage_options' );
	}
	if ( ! defined( 'STACKBOOST_VERSION' ) ) {
		define( 'STACKBOOST_VERSION', '1.0.0' );
	}
	if ( ! defined( 'STACKBOOST_PLUGIN_FILE' ) ) {
		define( 'STACKBOOST_PLUGIN_FILE', __FILE__ );
	}

	require_once __DIR__ . '/../stackboost-for-supportcandy/src/WordPress/Admin/Settings.php';
	require_once __DIR__ . '/../stackboost-for-supportcandy/src/Modules/UnifiedTicketMacro/Core.php';
	require_once __DIR__ . '/../stackboost-for-supportcandy/src/Modules/UnifiedTicketMacro/WordPress.php';

	use StackBoost\ForSupportCandy\WordPress\Admin\Settings;
	use StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Core;

	echo "=== RUNNING UTM RESPONSE PLACEMENT TESTS ===\n\n";

	$settings = Settings::get_instance();
	$core     = Core::get_instance();

	// Test 1: Sanitization of utm_response_placement
	echo "Test 1: Sanitization of utm_response_placement option...\n";
	$input_valid = [
		'page_slug'              => 'stackboost-utm',
		'utm_enabled'            => '1',
		'utm_response_placement' => 'below',
	];
	$sanitized = $settings->sanitize_settings( $input_valid );
	if ( isset( $sanitized['utm_response_placement'] ) && 'below' === $sanitized['utm_response_placement'] ) {
		echo "[PASS] 'below' placement correctly sanitized and preserved.\n";
	} else {
		echo "[FAIL] 'below' placement sanitization failed.\n";
		exit(1);
	}

	$input_mobile = [
		'page_slug'              => 'stackboost-utm',
		'utm_enabled'            => '1',
		'utm_response_placement' => 'mobile_only',
	];
	$sanitized_mobile = $settings->sanitize_settings( $input_mobile );
	if ( isset( $sanitized_mobile['utm_response_placement'] ) && 'mobile_only' === $sanitized_mobile['utm_response_placement'] ) {
		echo "[PASS] 'mobile_only' placement correctly sanitized and preserved.\n";
	} else {
		echo "[FAIL] 'mobile_only' placement sanitization failed.\n";
		exit(1);
	}

	$input_invalid = [
		'page_slug'              => 'stackboost-utm',
		'utm_enabled'            => '1',
		'utm_response_placement' => 'invalid_value',
	];
	$sanitized_invalid = $settings->sanitize_settings( $input_invalid );
	if ( isset( $sanitized_invalid['utm_response_placement'] ) && 'beside' === $sanitized_invalid['utm_response_placement'] ) {
		echo "[PASS] Invalid placement value safely defaulted to 'beside'.\n";
	} else {
		echo "[FAIL] Invalid placement sanitization failed.\n";
		exit(1);
	}

	// Test 2: Core HTML generation with 'beside', 'below', and 'mobile_only'
	echo "\nTest 2: HTML rendering output for response placement options...\n";

	// Setup mock custom fields
	$field_subject = new \WPSC\FieldTypeObject();
	WPSC_Custom_Field::$custom_fields = [
		'subject' => $field_subject,
	];

	$ticket = new WPSC_Ticket();

	// Case 2a: Beside (Default)
	global $mock_options;
	$mock_options['stackboost_settings'] = [
		'utm_enabled'            => 1,
		'utm_columns'            => [ 'subject' ],
		'utm_response_placement' => 'beside',
	];

	$html_beside_table = $core->build_live_utm_html( $ticket, 'table' );
	if ( str_istr( $html_beside_table, '<strong>Subject:</strong>' ) && str_istr( $html_beside_table, 'Help with billing</td>' ) ) {
		echo "[PASS] Table format - 'beside' layout renders side-by-side cells.\n";
	} else {
		echo "[FAIL] Table format - 'beside' layout output unexpected: " . $html_beside_table . "\n";
		exit(1);
	}

	// Helper helper function for PHP < 8 compatibility
	function str_istr( $haystack, $needle ) {
		return false !== stripos( $haystack, $needle );
	}

	// Case 2b: Below
	$mock_options['stackboost_settings']['utm_response_placement'] = 'below';
	$html_below_table = $core->build_live_utm_html( $ticket, 'table' );
	if ( str_istr( $html_below_table, 'colspan="2"' ) && str_istr( $html_below_table, 'Subject:' ) && str_istr( $html_below_table, 'Help with billing' ) ) {
		echo "[PASS] Table format - 'below' layout renders stacked cell with colspan='2'.\n";
	} else {
		echo "[FAIL] Table format - 'below' layout output unexpected: " . $html_below_table . "\n";
		exit(1);
	}

	$html_below_list = $core->build_live_utm_html( $ticket, 'list' );
	if ( str_istr( $html_below_list, 'display:block;' ) && str_istr( $html_below_list, 'Subject:' ) ) {
		echo "[PASS] List format - 'below' layout renders block label on top of response.\n";
	} else {
		echo "[FAIL] List format - 'below' layout output unexpected: " . $html_below_list . "\n";
		exit(1);
	}

	// Case 2c: Mobile Only
	$mock_options['stackboost_settings']['utm_response_placement'] = 'mobile_only';
	$html_mobile_table = $core->build_live_utm_html( $ticket, 'table' );
	if ( str_istr( $html_mobile_table, 'utm-mobile-below' ) && str_istr( $html_mobile_table, 'display: inline-block' ) && str_istr( $html_mobile_table, 'sb-utm-label' ) && str_istr( $html_mobile_table, 'sb-utm-val' ) ) {
		echo "[PASS] Table format - 'mobile_only' layout includes fluid responsive containers.\n";
	} else {
		echo "[FAIL] Table format - 'mobile_only' layout output unexpected: " . $html_mobile_table . "\n";
		exit(1);
	}

	$html_mobile_list = $core->build_live_utm_html( $ticket, 'list' );
	if ( str_istr( $html_mobile_list, 'utm-mobile-below' ) && str_istr( $html_mobile_list, 'sb-utm-label' ) && str_istr( $html_mobile_list, 'sb-utm-val' ) ) {
		echo "[PASS] List format - 'mobile_only' layout includes responsive classes.\n";
	} else {
		echo "[FAIL] List format - 'mobile_only' layout output unexpected: " . $html_mobile_list . "\n";
		exit(1);
	}

	echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";
}
