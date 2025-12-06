<?php

// Mock functions
function sanitize_text_field( $str ) {
    return trim( filter_var( $str, FILTER_SANITIZE_STRING ) );
}

// Logic to be implemented in MetaBoxes.php
function mock_save_logic( $input ) {
    $digits = preg_replace( '/\D/', '', $input );
    if ( strlen( $digits ) === 10 ) {
        return $digits;
    } elseif ( strlen( $digits ) === 11 && substr( $digits, 0, 1 ) === '1' ) {
        return substr( $digits, 1 );
    } else {
        return sanitize_text_field( $input );
    }
}

// Logic to be implemented in DirectoryService.php
function mock_format_display( $stored_value ) {
    // Only format if it looks like a stored US number (exactly 10 digits)
    if ( preg_match( '/^\d{10}$/', $stored_value ) ) {
        return '(' . substr( $stored_value, 0, 3 ) . ') ' . substr( $stored_value, 3, 3 ) . '-' . substr( $stored_value, 6 );
    }
    return $stored_value;
}

function mock_generate_tel( $stored_value ) {
    $clean_number = preg_replace( '/[^0-9+]/', '', $stored_value );
    return 'tel:' . $clean_number;
}

// Test Cases
$test_cases = [
    'US Raw' => '5551234567',
    'US Formatted' => '(555) 123-4567',
    'US 11-digit' => '1-555-123-4567',
    'Intl +44' => '+44 20 1234 5678',
    'Intl 011' => '011 44 20 1234 5678',
    'Intl Random' => '+123 456 789 012',
    'Short Number' => '12345'
];

echo "Running Tests...\n\n";

foreach ( $test_cases as $name => $input ) {
    $stored = mock_save_logic( $input );
    $display = mock_format_display( $stored );
    $tel = mock_generate_tel( $stored );

    echo "Test Case: $name\n";
    echo "  Input:    '$input'\n";
    echo "  Stored:   '$stored'\n";
    echo "  Display:  '$display'\n";
    echo "  Tel Link: '$tel'\n";
    echo "--------------------------------------------------\n";
}
