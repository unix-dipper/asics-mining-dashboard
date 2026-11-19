<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $products = [
        ['Antminer DR7 127 TH', 10.00, 12.60, 1.80, 127, 7, '▧'],
        ['Antminer KA3 166 TH', 30.00, 43.50, 2.90, 166, 7, '▦'],
        ['Antminer S21 Pro 234 TH', 100.00, 180.00, 12.00, 234, 15, '▤'],
        ['Antminer S23 318 TH', 250.00, 525.00, 35.00, 318, 15, '▥'],
        ['Whatsminer M63S+ 390 TH', 500.00, 1500.00, 50.00, 390, 30, '⬢'],
        ['Antminer S21 XP Hyd 473 TH', 1000.00, 4200.00, 140.00, 473, 30, '✦'],
        ['VIP Daily Miner', 9.99, 5.00, 0.1667, 95, 1, '◆'],
        ['VIP Nova Hash 24H', 19.99, 12.00, 0.40, 110, 30, '✦'],
        ['VIP Quantum Miner', 29.99, 20.00, 0.6667, 125, 30, '⬡'],
        ['VIP Elite Satoshi', 49.99, 35.00, 1.1667, 140, 30, '◈'],
        ['VIP Aurora ASIC', 79.99, 60.00, 2.00, 155, 30, '◆'],
    ];

    $findProduct = $pdo->prepare('SELECT id FROM products WHERE name = ?');
    $insertProduct = $pdo->prepare(
        'INSERT INTO products (name, price_usd, total_return_usd, daily_profit_usd, hashrate, duration_days, icon, active, vip_only)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)'
    );
    $updateProduct = $pdo->prepare(
        'UPDATE products SET price_usd=?, total_return_usd=?, daily_profit_usd=?, hashrate=?, duration_days=?, icon=?, active=1, vip_only=? WHERE id=?'
    );
    $pdo->exec("UPDATE products SET active=0 WHERE name IN ('Antminer DR7 127 Th','Antminer KA3 (166 Th)','Bitmain Antminer S23','Bitmain Antminer S21 Pro','Goldshell Mini-DOGE','Whatsminer M60S','Avalon Miner A1466','IceRiver AL3','VIP Black Diamond 24H')");

    foreach ($products as $product) {
        [$name, $price, $total, $daily, $hashrate, $duration, $icon] = $product;
        $vipOnly = str_starts_with($name, 'VIP ') ? 1 : 0;
        $findProduct->execute([$name]);
        $productId = $findProduct->fetchColumn();
        if ($productId) {
            $updateProduct->execute([$price, $total, $daily, $hashrate, $duration, $icon, $vipOnly, $productId]);
        } else {
            $insertProduct->execute([$name, $price, $total, $daily, $hashrate, $icon, $vipOnly]);
        }
    }

    $pdo->prepare('DELETE FROM users WHERE email = ?')->execute(['demo@asics.test']);
    $pdo->prepare('DELETE FROM users WHERE email = ?')->execute(['demo@example.com']);

    $adminEmail = 'admin@asics.test';
    $findAdmin = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $findAdmin->execute([$adminEmail]);
    if (!$findAdmin->fetchColumn()) {
        $pdo->prepare('INSERT INTO users (name,email,password_hash,account_id,uid,balance,is_admin,referral_code) VALUES (?,?,?,?,?,?,1,?)')
            ->execute(['Administrator', $adminEmail, password_hash('Admin123456!', PASSWORD_DEFAULT), '9000001', 'ADMIN-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12)), 0, 'ADMIN-ASICS']);
    }

    $pdo->commit();
    echo "Seed completed: canonical mining and VIP plans are ready. No demo account was created.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $error->getMessage() . "\n");
    exit(1);
}
