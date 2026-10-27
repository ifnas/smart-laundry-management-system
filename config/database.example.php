<?php
declare(strict_types=1);

/**
 * Smart Laundry Booking & Delivery Management System
 * Database Configuration Template
 *
 * INSTRUCTIONS:
 * 1. Copy this file to `config/database.php`:
 *    cp config/database.example.php config/database.php
 * 2. Update the credentials below or define environment variables.
 * 3. Never commit `config/database.php` with real credentials to version control.
 */

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        // Read configuration from environment variables or fall back to local development defaults
        $host     = getenv('DB_HOST') ?: '127.0.0.1';
        $port     = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_NAME') ?: 'smart_laundry_db';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
        $timezone = getenv('DB_TIMEZONE') ?: '+05:30';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            $pdo->exec("SET time_zone = '{$timezone}'");
        } catch (PDOException $e) {
            // Log message securely in production; avoid exposing credentials in user-facing error output
            error_log('Database Connection Error: ' . $e->getMessage());
            throw new PDOException('Database connection failed. Please verify configuration settings.');
        }
    }
    return $pdo;
}

