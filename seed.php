<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $products = [
        ['Antminer DR7 127 Th', 10.00, 19.00, 2.71, 127, '▧'],
        ['Antminer KA3 (166 Th)', 30.00, 61.00, 3.39, 166, '▦'],
        ['Bitmain Antminer S23', 50.00, 111.00, 3.70, 195, '▥'],
        ['Bitmain Antminer S21 Pro', 100.00, 350.00, 11.67, 234, '▤'],
        ['Goldshell Mini-DOGE', 160.00, 520.00, 17.33, 285, '◆'],
        ['Whatsminer M60S', 225.00, 780.00, 26.00, 340, '⬡'],
        ['Avalon Miner A1466', 320.00, 1100.00, 36.67, 410, '◈'],
        ['IceRiver AL3', 480.00, 1600.00, 53.33, 520, '✦'],
        ['Whatsminer M63S+', 680.00, 2350.00, 78.33, 680, '⬢'],
        ['VIP Daily Miner', 9.99, 5.00, 0.1667, 95, '◆'],
    ];

    $findProduct = $pdo->prepare('SELECT id FROM products WHERE name = ?');
    $insertProduct = $pdo->prepare(
        'INSERT INTO products (name, price_usd, total_return_usd, daily_profit_usd, hashrate, icon, active, vip_only)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
    );
    $updateProduct = $pdo->prepare(
        'UPDATE products SET price_usd=?, total_return_usd=?, daily_profit_usd=?, hashrate=?, icon=?, active=1, vip_only=? WHERE id=?'
    );

    foreach ($products as $product) {
        [$name, $price, $total, $daily, $hashrate, $icon] = $product;
        $vipOnly = str_starts_with($name, 'VIP ') ? 1 : 0;
        $findProduct->execute([$name]);
        $productId = $findProduct->fetchColumn();
        if ($productId) {
            $updateProduct->execute([$price, $total, $daily, $hashrate, $icon, $vipOnly, $productId]);
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
    echo "Seed completed: 4 products are ready. No demo account was created.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $error->getMessage() . "\n");
    exit(1);
}
