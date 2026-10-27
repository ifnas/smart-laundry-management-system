<?php
$out = fopen('php://temp', 'r+');
fputcsv($out, ['ID', 'Expense Date', 'Category', 'Title', 'Amount (LKR)', 'Recorded By', 'Notes']);
rewind($out);
$s = stream_get_contents($out);
echo "Result: [" . bin2hex($s) . "]\n";
echo "Text: $s\n";
var_dump(str_contains($s, 'ID,Expense Date,Category,Title,Amount (LKR)'));

