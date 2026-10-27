<?php
$dirs = ['.', 'admin', 'customer', 'delivery', 'includes', 'actions', 'tests', 'tools'];
$files = [];
foreach ($dirs as $d) {
    foreach (glob(__DIR__ . '/../' . $d . '/*.php') as $f) {
        $files[] = $f;
    }
}
$errors = 0;
foreach ($files as $f) {
    $cmd = 'C:\\xampp\\php\\php.exe -l ' . escapeshellarg($f) . ' 2>&1';
    exec($cmd, $output, $code);
    if ($code !== 0) {
        echo "FAIL: $f\n" . implode("\n", $output) . "\n";
        $errors++;
    }
    $output = [];
}
if ($errors === 0) {
    echo "SUCCESS: All " . count($files) . " PHP files passed syntax check.\n";
} else {
    echo "FAILED: $errors files had syntax errors.\n";
    exit(1);
}

