<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Colombo');
ini_set('display_errors','0');
ini_set('session.use_strict_mode','1');
// Optional portable-development override; XAMPP uses its normal session folder.
if(getenv('SESSION_PATH')) session_save_path(getenv('SESSION_PATH'));
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/functions.php';
set_exception_handler(function(Throwable $e): void {
    error_log((string)$e);
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Smart Laundry</title><h1>Unable to complete this request</h1><p>Please check that MySQL is running and the database has been installed. See README.md for setup instructions.</p>';
});
if (!isset($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
