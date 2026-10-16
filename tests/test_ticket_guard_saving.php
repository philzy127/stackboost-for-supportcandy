<?php

echo "Testing Ticket Guard Rule JSON Payload Parsing:\n";

// 1. Simulate JSON payload created by JS JSON.stringify() sent over AJAX POST
// Raw string sent from JS client for a rule with quotes, newlines, and HTML formatting:
$raw_json_client = '[{"id":"rule_123","name":"Steer \"Billing\" Keywords","enabled":true,"target_context":"new_ticket","monitored_field":"description","keywords":["refund\\ninvoice"],"swap_field":"category","swap_value":"2","actions":{"disable_submit":true,"show_modal":true,"show_inline_warning":true,"auto_swap_category":false},"messaging":{"modal_title":"Looking for \"Billing\" Support?","modal_body":"<p>Please select <strong>Billing</strong>.</p>","inline_warning":"<strong>Notice:</strong> Keyword \"refund\" detected.","inline_level":"alert","submit_disabled_message":"Submit disabled."}}]';

// WordPress slashes $_POST data upon receiving:
$post_rules = addslashes( $raw_json_client );

// Request::get_post( 'rules', '[]', 'raw' ) unslashes once:
$unslashed_once = stripslashes( $post_rules );

// Decode without second unslash
$rules_decoded = json_decode( $unslashed_once, true );

if ( is_array( $rules_decoded ) && count( $rules_decoded ) === 1 ) {
    echo "[PASS] Rule JSON payload decoded successfully after single unslash\n";
    $rule = $rules_decoded[0];
    if ( $rule['name'] === 'Steer "Billing" Keywords' ) {
        echo "[PASS] Escaped quotes in rule name correctly preserved\n";
    } else {
        echo "[FAIL] Escaped quotes corrupted in rule name: Got '{$rule['name']}'\n";
        exit(1);
    }
    if ( $rule['messaging']['inline_warning'] === '<strong>Notice:</strong> Keyword "refund" detected.' ) {
        echo "[PASS] HTML formatting and quotes in inline_warning preserved\n";
    } else {
        echo "[FAIL] Inline warning message corrupted\n";
        exit(1);
    }
} else {
    echo "[FAIL] Failed to decode rule JSON payload\n";
    exit(1);
}

// 2. Test that double unslashing would corrupt the JSON payload (verifying bug cause)
$unslashed_twice = stripslashes( $unslashed_once );
$rules_decoded_twice = json_decode( $unslashed_twice, true );

if ( $rules_decoded_twice === null ) {
    echo "[PASS] Confirmed: Double unslashing causes json_decode() to fail (returns null)\n";
} else {
    echo "[FAIL] Double unslashing did not return null as expected\n";
    exit(1);
}

echo "All Ticket Guard saving tests passed successfully!\n";
