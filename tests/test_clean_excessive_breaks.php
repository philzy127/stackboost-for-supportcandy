<?php

require_once __DIR__ . '/../stackboost-for-supportcandy/src/Modules/QolEnhancements/Core.php';

use StackBoost\ForSupportCandy\Modules\QolEnhancements\Core;

$core = new Core();

echo "Testing Line Break Cleanup Logic:\n";

// Case 1: Multiple BR tags
$input1 = "Hello<br><br><br>World";
$output1 = $core->strip_excessive_breaks($input1);
if ($output1 === "Hello<br>World") {
    echo "[PASS] Multiple BR tags reduced to single BR\n";
} else {
    echo "[FAIL] Multiple BR tags: Got '{$output1}'\n";
}

// Case 2: Multiple BR tags with spaces & closing slashes
$input2 = "Line 1 <br /> \n <br> \t <br/> Line 2";
$output2 = $core->strip_excessive_breaks($input2);
if ($output2 === "Line 1<br>Line 2") {
    echo "[PASS] Multiple BR tags with spaces/newlines reduced to single BR\n";
} else {
    echo "[FAIL] Multiple BR tags with whitespace: Got '{$output2}'\n";
}

// Case 3: Multiple HR tags
$input3 = "Header<hr><hr /><hr  />Footer";
$output3 = $core->strip_excessive_breaks($input3);
if ($output3 === "Header<br>Footer") {
    echo "[PASS] Multiple HR tags reduced\n";
} else {
    echo "[FAIL] Multiple HR tags: Got '{$output3}'\n";
}

// Case 4: Non-string / Empty input
if ($core->strip_excessive_breaks(null) === null && $core->strip_excessive_breaks('') === '') {
    echo "[PASS] Non-string / Empty inputs handled safely\n";
} else {
    echo "[FAIL] Non-string / Empty inputs handling failed\n";
}
