<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

function jsonBody(): array {
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : [];
}
function response(array $body, int $status = 200): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}
function requireUser(): array {
    $current = user();
    if (!$current) response(['error' => 'Authentication required'], 401);
    return $current;
}
function publicUser(array $row): array {
    return [
        'id' => (int) $row['id'], 'name' => $row['name'], 'email' => $row['email'],
        'accountId' => $row['account_id'], 'uid' => $row['uid'], 'balance' => (float) $row['balance'],
        'initials' => strtoupper(substr(implode('', array_map(fn($part) => $part[0] ?? '', preg_split('/\s+/', $row['name']))), 0, 2)),
    ];
}
function pathParts(): array {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $path = preg_replace('#^/api\.php/?#', '', $path);
    return array_values(array_filter(explode('/', $path), 'strlen'));
}
function product(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}
function productRows(array $rows): array {
    return array_map(fn($row) => [
        'id' => (int) $row['id'], 'name' => $row['name'], 'price' => (float) $row['price_usd'],
        'total' => (float) $row['total_return_usd'], 'daily' => (float) $row['daily_profit_usd'],
        'hashrate' => (float) $row['hashrate'], 'icon' => $row['icon'],
    ], $rows);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$parts = pathParts();
$pdo = db();
$body = in_array($method, ['POST', 'PATCH'], true) ? jsonBody() : [];

try {
    if ($parts === ['auth', 'login'] && $method === 'POST') {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([trim((string) ($body['email'] ?? ''))]);
        $found = $stmt->fetch();
        if (!$found || !password_verify((string) ($body['password'] ?? ''), $found['password_hash'])) response(['error' => 'Invalid credentials'], 401);
        $_SESSION['user_id'] = (int) $found['id'];
        response(['user' => publicUser($found)]);
    }
    if ($parts === ['auth', 'register'] && $method === 'POST') {
        $name = trim((string) ($body['name'] ?? '')); $email = strtolower(trim((string) ($body['email'] ?? ''))); $password = (string) ($body['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) response(['error' => 'Valid name, email and six-character password are required'], 422);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, account_id, uid) VALUES (?, ?, ?, ?, ?)');
        try { $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), (string) random_int(1000000, 9999999), strtoupper(bin2hex(random_bytes(6)))]); }
        catch (PDOException $error) { response(['error' => 'Email already exists'], 409); }
        $userId = (int) $pdo->lastInsertId(); $_SESSION['user_id'] = $userId;
        $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)')->execute([$userId, 'Account created', 'Your mining account is ready.']);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?'); $stmt->execute([$userId]);
        response(['user' => publicUser($stmt->fetch())], 201);
    }
    if ($parts === ['auth', 'logout'] && $method === 'POST') { $_SESSION = []; session_destroy(); response(['ok' => true]); }
    $current = requireUser();
    if ($parts === ['me'] && $method === 'GET') response(['user' => publicUser($current)]);
    if ($parts === ['me'] && $method === 'PATCH') {
        $name = trim((string) ($body['name'] ?? $current['name'])); $email = strtolower(trim((string) ($body['email'] ?? $current['email'])));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) response(['error' => 'Valid name and email are required'], 422);
        try { $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $current['id']]); }
        catch (PDOException $error) { response(['error' => 'Email already exists'], 409); }
        $current['name'] = $name; $current['email'] = $email; response(['user' => publicUser($current)]);
    }
    if ($parts === ['me', 'password'] && $method === 'PATCH') {
        if (!password_verify((string) ($body['currentPassword'] ?? ''), $current['password_hash'])) response(['error' => 'Current password is incorrect'], 422);
        if (strlen((string) ($body['newPassword'] ?? '')) < 6) response(['error' => 'New password must be at least six characters'], 422);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($body['newPassword'], PASSWORD_DEFAULT), $current['id']]);
        response(['ok' => true]);
    }
    if ($parts === ['dashboard']) {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(p.hashrate),0) hashrate, COALESCE(SUM(p.daily_profit_usd),0) daily, COALESCE(SUM(p.total_return_usd),0) total FROM purchases o JOIN products p ON p.id=o.product_id WHERE o.user_id=? AND o.status="active"');
        $stmt->execute([$current['id']]); $stats = $stmt->fetch();
        response(['balance' => (float) $current['balance'], 'hashrate' => (float) $stats['hashrate'], 'dailyProfit' => (float) $stats['daily'], 'totalProfit' => (float) $stats['total']]);
    }
    if ($parts === ['miners'] || $parts === ['products']) response(productRows($pdo->query('SELECT * FROM products WHERE active=1 ORDER BY id')->fetchAll()));
    if ($parts === ['contracts']) {
        $stmt = $pdo->prepare('SELECT o.*, p.name, p.hashrate, p.price_usd price, p.total_return_usd total, p.daily_profit_usd daily, p.icon FROM purchases o JOIN products p ON p.id=o.product_id WHERE o.user_id=? ORDER BY o.id DESC');
        $stmt->execute([$current['id']]); response(array_map(fn($row) => ['id'=>(int)$row['id'], 'name'=>$row['name'], 'hashrate'=>(float)$row['hashrate'], 'price'=>(float)$row['price'], 'total'=>(float)$row['total'], 'daily'=>(float)$row['daily'], 'icon'=>$row['icon'], 'status'=>$row['status'], 'earningsPaid'=>(float)$row['earnings_paid'], 'purchasedAt'=>$row['purchased_at']], $stmt->fetchAll()));
    }
    if ($parts === ['transactions']) { $stmt=$pdo->prepare('SELECT id,type,amount,status,reference,created_at date FROM transactions WHERE user_id=? ORDER BY id DESC'); $stmt->execute([$current['id']]); response(array_map(fn($row)=>['id'=>(int)$row['id'],'type'=>$row['type'],'amount'=>(float)$row['amount'],'status'=>$row['status'],'reference'=>$row['reference'],'date'=>$row['date']],$stmt->fetchAll())); }
    if ($parts === ['notifications']) { $stmt=$pdo->prepare('SELECT id,title,message,created_at date,read_at FROM notifications WHERE user_id=? ORDER BY id DESC'); $stmt->execute([$current['id']]); response($stmt->fetchAll()); }
    if ($parts === ['payments', 'invoice'] && $method === 'POST') {
        $item = product((int) ($body['productId'] ?? 0)); if (!$item) response(['error'=>'Product not found'],404);
        $rate = (float) config('BTC_USD_RATE');
        if ($rate <= 0) $rate = BTC_USD_RATE;
        $cryptoAmount = round((float) $item['price_usd'] / $rate, 8);
        $code = 'INV-' . strtoupper(bin2hex(random_bytes(5)));
        $address = config('CRYPTO_PAYMENT_ADDRESS') ?: DEFAULT_PAYMENT_ADDRESS;
        $pdo->prepare('INSERT INTO payments (user_id,product_id,invoice_code,network,address,amount_usd,amount_crypto) VALUES (?,?,?,?,?,?,?)')->execute([$current['id'],$item['id'],$code,PAYMENT_NETWORK,$address,$item['price_usd'],$cryptoAmount]);
        response(['id'=>(int)$pdo->lastInsertId(),'invoiceCode'=>$code,'network'=>PAYMENT_NETWORK,'address'=>$address,'amountUsd'=>(float)$item['price_usd'],'amountCrypto'=>$cryptoAmount,'status'=>'pending','product'=>productRows([$item])[0]],201);
    }
    if (count($parts) === 3 && $parts[0] === 'payments' && $parts[1] === 'invoice' && $method === 'GET') {
        $stmt=$pdo->prepare('SELECT pay.*, p.name product_name FROM payments pay JOIN products p ON p.id=pay.product_id WHERE pay.id=? AND pay.user_id=?'); $stmt->execute([(int)$parts[2],$current['id']]); $payment=$stmt->fetch(); if(!$payment) response(['error'=>'Invoice not found'],404); response($payment);
    }
    if ($parts === ['payments', 'webhook'] && $method === 'POST') {
        $secret = config('PAYMENT_WEBHOOK_SECRET'); $raw=file_get_contents('php://input'); $signature=$_SERVER['HTTP_X_PAYMENT_SIGNATURE'] ?? '';
        if ($secret !== '' && !hash_equals(hash_hmac('sha256',$raw,$secret),$signature)) response(['error'=>'Invalid webhook signature'],401);
        $payload=json_decode($raw,true) ?: $body; $stmt=$pdo->prepare('SELECT * FROM payments WHERE invoice_code=?'); $stmt->execute([(string)($payload['invoiceCode']??'')]); $payment=$stmt->fetch(); if(!$payment) response(['error'=>'Invoice not found'],404);
        if ($payment['status'] !== 'paid') { $pdo->beginTransaction(); $pdo->prepare('UPDATE payments SET status="paid", txid=?, paid_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(string)($payload['txid']??''),$payment['id']]); $pdo->prepare('INSERT INTO purchases (user_id,product_id,payment_id) VALUES (?,?,?)')->execute([$payment['user_id'],$payment['product_id'],$payment['id']]); $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$payment['user_id'],'Crypto purchase',-$payment['amount_usd'],'completed',$payment['invoice_code']]); $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$payment['user_id'],'Payment confirmed','Your mining product is now active.']); $pdo->commit(); }
        response(['ok'=>true,'status'=>'paid']);
    }
    response(['error'=>'Not found'],404);
} catch (Throwable $error) {
    response(['error' => 'Server error', 'detail' => config('APP_DEBUG') === 'true' ? $error->getMessage() : null], 500);
}
