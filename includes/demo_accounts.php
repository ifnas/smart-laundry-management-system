<?php
declare(strict_types=1);

// Local development credentials only. This helper must never run via HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function demo_accounts(): array {
    return [
        ['name' => 'Admin', 'email' => 'admin@laundry.com', 'role' => 'admin', 'password' => 'Admin@123'],
        ['name' => 'Demo Customer', 'email' => 'customer@laundry.com', 'role' => 'customer', 'password' => 'Customer@123'],
        ['name' => 'Demo Driver', 'email' => 'delivery@laundry.com', 'role' => 'delivery', 'password' => 'Delivery@123'],
    ];
}

function check_demo_accounts(PDO $pdo): array {
    $select = $pdo->prepare('SELECT id, password, role, is_active FROM users WHERE email = ?');
    $results = [];
    foreach (demo_accounts() as $demo) {
        $select->execute([$demo['email']]);
        $user = $select->fetch(PDO::FETCH_ASSOC);
        $results[] = [
            'email' => $demo['email'],
            'exists' => (bool) $user,
            'password_valid' => $user && password_verify($demo['password'], $user['password']),
            'role_valid' => $user && $user['role'] === $demo['role'],
            'active' => $user && (int) $user['is_active'] === 1,
        ];
    }
    return $results;
}

function seed_demo_accounts(PDO $pdo, bool $repair = false): array {
    // Allow the integration test to wrap the operation in a rollback-only transaction.
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT id, password, role, is_active FROM users WHERE email = ? FOR UPDATE');
        $insert = $pdo->prepare('INSERT INTO users(name,email,phone,password,role,address) VALUES(?,?,?,?,?,?)');
        $update = $pdo->prepare('UPDATE users SET password=?,role=?,is_active=1 WHERE id=?');
        $results = [];
        foreach (demo_accounts() as $demo) {
            $select->execute([$demo['email']]);
            $user = $select->fetch(PDO::FETCH_ASSOC);
            $outcome = 'unchanged';
            if (!$user) {
                $insert->execute([$demo['name'], $demo['email'], '0771234567', password_hash($demo['password'], PASSWORD_DEFAULT), $demo['role'], '25 Flower Road, Colombo']);
                $outcome = 'created';
            } elseif ($repair) {
                $hash = $user['password'];
                if (!password_verify($demo['password'], $hash) || password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $hash = password_hash($demo['password'], PASSWORD_DEFAULT);
                }
                if ($hash !== $user['password'] || $user['role'] !== $demo['role'] || (int) $user['is_active'] !== 1) {
                    // Update the existing ID: never REPLACE, delete, or overwrite contact details.
                    $update->execute([$hash, $demo['role'], $user['id']]);
                    $outcome = 'repaired';
                }
            }
            $results[$demo['email']] = $outcome;
        }
        foreach (check_demo_accounts($pdo) as $check) {
            if (!$check['password_valid'] || !$check['role_valid'] || !$check['active']) {
                throw new RuntimeException($check['email'] . ' does not match the demo login. For a local demo reset, run setup.php --repair-demo.');
            }
        }
        if ($ownsTransaction) $pdo->commit();
        return $results;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
