<?php
require __DIR__ . '/../config/database.php';
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

$timestamp = date('Ymd_His');
$backupFile = $backupDir . '/backup_smart_laundry_db_' . $timestamp . '.sql';

$pdo = db();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$sql = "-- Smart Laundry Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

$counts = [];

foreach ($tables as $t) {
    $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
    $sql .= "DROP TABLE IF EXISTS `$t`;\n" . $create . ";\n\n";
    
    $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
    $counts[$t] = count($rows);
    if ($rows) {
        $cols = array_keys($rows[0]);
        $escapedCols = array_map(fn($c) => "`$c`", $cols);
        $colList = implode(', ', $escapedCols);
        
        $sql .= "INSERT INTO `$t` ($colList) VALUES\n";
        $valLines = [];
        foreach ($rows as $r) {
            $vals = [];
            foreach ($r as $v) {
                if ($v === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = $pdo->quote((string)$v);
                }
            }
            $valLines[] = '(' . implode(', ', $vals) . ')';
        }
        $sql .= implode(",\n", $valLines) . ";\n\n";
    }
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

file_put_contents($backupFile, $sql);

echo "Backup created successfully: $backupFile (" . strlen($sql) . " bytes)\n";
echo "Record counts:\n";
foreach ($counts as $tbl => $cnt) {
    echo "  $tbl: $cnt rows\n";
}

