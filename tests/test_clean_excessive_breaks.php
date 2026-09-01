<?php

require_once __DIR__ . '/../stackboost-for-supportcandy/src/Modules/QolEnhancements/Core.php';

use StackBoost\ForSupportCandy\Modules\QolEnhancements\Core;

$core = new Core();

echo "Testing Clean Macro Spacing (<br><hr><br>) Logic:\n";

// Case 1: Standard <br><hr><br> pattern
$input1 = "Header<br><hr><br>Footer";
$output1 = $core->strip_br_hr_tags($input1);
$expected1 = 'Header<hr style="margin:4px 0 !important; border:0; border-top:1px solid #ccc;">Footer';
if ($output1 === $expected1) {
    echo "[PASS] Standard <br><hr><br> pattern replaced correctly\n";
} else {
    echo "[FAIL] Standard pattern: Got '{$output1}'\n";
}

// Case 2: Variations with spaces and closing slashes
$input2 = "Header <br /> \n <hr style=\"border:1px solid red;\"> \t <br/> Footer";
$output2 = $core->strip_br_hr_tags($input2);
$expected2 = 'Header <hr style="margin:4px 0 !important; border:0; border-top:1px solid #ccc;"> Footer';
if ($output2 === $expected2) {
    echo "[PASS] Variations with spaces, newlines, and hr attributes replaced correctly\n";
} else {
    echo "[FAIL] Variations: Got '{$output2}'\n";
}

// Case 3: Backward compatibility method alias
if ($core->strip_excessive_breaks($input1) === $expected1) {
    echo "[PASS] Backward compatibility method strip_excessive_breaks() works identically\n";
} else {
    echo "[FAIL] Backward compatibility method failed\n";
}

// Case 4: Non-string / Empty input
if ($core->strip_br_hr_tags(null) === null && $core->strip_br_hr_tags('') === '') {
    echo "[PASS] Non-string / Empty inputs handled safely\n";
} else {
    echo "[FAIL] Non-string / Empty inputs handling failed\n";
}
