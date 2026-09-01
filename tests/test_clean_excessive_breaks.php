<?php

require_once __DIR__ . '/../stackboost-for-supportcandy/src/Modules/QolEnhancements/Core.php';

use StackBoost\ForSupportCandy\Modules\QolEnhancements\Core;

$core = new Core();

echo "Testing Line Break and HR Cleanup Logic:\n";

// Case 1: Multiple BR tags (BR only)
$input1 = "Hello<br><br><br>World";
$output1 = $core->strip_excessive_breaks($input1);
if ($output1 === "Hello<br>World") {
    echo "[PASS] strip_excessive_breaks(): Multiple BR tags reduced to single BR\n";
} else {
    echo "[FAIL] strip_excessive_breaks(): Got '{$output1}'\n";
}

// Case 2: Multiple BR tags with spaces & closing slashes
$input2 = "Line 1 <br /> \n <br> \t <br/> Line 2";
$output2 = $core->strip_excessive_breaks($input2);
if ($output2 === "Line 1<br>Line 2") {
    echo "[PASS] strip_excessive_breaks(): Multiple BR tags with whitespace reduced to single BR\n";
} else {
    echo "[FAIL] strip_excessive_breaks(): Got '{$output2}'\n";
}

// Case 3: Multiple HR tags (HR only)
$input3 = "Header<hr><hr /><hr  />Footer";
$output3 = $core->strip_excessive_hrs($input3);
if ($output3 === "Header<hr>Footer") {
    echo "[PASS] strip_excessive_hrs(): Multiple HR tags reduced to single HR\n";
} else {
    echo "[FAIL] strip_excessive_hrs(): Got '{$output3}'\n";
}

// Case 4: Independent application (HRs preserved when only stripping BRs)
$input4 = "Top<br><br>Middle<hr><hr>Bottom";
$output4_br = $core->strip_excessive_breaks($input4);
if ($output4_br === "Top<br>Middle<hr><hr>Bottom") {
    echo "[PASS] strip_excessive_breaks(): Leaves HR tags untouched\n";
} else {
    echo "[FAIL] strip_excessive_breaks(): Got '{$output4_br}'\n";
}

$output4_hr = $core->strip_excessive_hrs($input4);
if ($output4_hr === "Top<br><br>Middle<hr>Bottom") {
    echo "[PASS] strip_excessive_hrs(): Leaves BR tags untouched\n";
} else {
    echo "[FAIL] strip_excessive_hrs(): Got '{$output4_hr}'\n";
}

// Case 5: Sequential application of both
$output4_both = $core->strip_excessive_hrs($core->strip_excessive_breaks($input4));
if ($output4_both === "Top<br>Middle<hr>Bottom") {
    echo "[PASS] Both cleaners applied sequentially produce clean HTML\n";
} else {
    echo "[FAIL] Both cleaners: Got '{$output4_both}'\n";
}

// Case 6: Non-string / Empty input
if ($core->strip_excessive_breaks(null) === null && $core->strip_excessive_hrs('') === '') {
    echo "[PASS] Non-string / Empty inputs handled safely\n";
} else {
    echo "[FAIL] Non-string / Empty inputs handling failed\n";
}
