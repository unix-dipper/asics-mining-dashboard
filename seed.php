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
    ];

    $findProduct = $pdo->prepare('SELECT id FROM products WHERE name = ?');
    $insertProduct = $pdo->prepare(
        'INSERT INTO products (name, price_usd, total_return_usd, daily_profit_usd, hashrate, icon, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );
    $updateProduct = $pdo->prepare(
        'UPDATE products SET price_usd=?, total_return_usd=?, daily_profit_usd=?, hashrate=?, icon=?, active=1 WHERE id=?'
    );

    foreach ($products as $product) {
        [$name, $price, $total, $daily, $hashrate, $icon] = $product;
        $findProduct->execute([$name]);
        $productId = $findProduct->fetchColumn();
        if ($productId) {
            $updateProduct->execute([$price, $total, $daily, $hashrate, $icon, $productId]);
        } else {
            $insertProduct->execute([$name, $price, $total, $daily, $hashrate, $icon]);
        }
    }

    $pdo->prepare('DELETE FROM users WHERE email = ?')->execute(['demo@asics.test']);

    $pdo->commit();
    echo "Seed completed: 4 products are ready. No demo account was created.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $error->getMessage() . "\n");
    exit(1);
}
