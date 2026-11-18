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

    $findDemo = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $findDemo->execute(['demo@asics.test']);
    $demoId = $findDemo->fetchColumn();

    if (!$demoId) {
        $insertDemo = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, account_id, uid, balance)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertDemo->execute([
            'Demo User',
            'demo@asics.test',
            password_hash('demo123', PASSWORD_DEFAULT),
            '0505050',
            '8358-4562-4521',
            400,
        ]);
        $demoId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO transactions (user_id, type, amount, status, reference)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$demoId, 'Welcome bonus', 400, 'completed', 'WELCOME']);

        $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message)
             VALUES (?, ?, ?)'
        )->execute([$demoId, 'Welcome to ASICs Mining', 'Your account is ready to start mining.']);
    }

    $pdo->commit();
    echo "Seed completed: demo account and 4 products are ready.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $error->getMessage() . "\n");
    exit(1);
}
