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
        'isAdmin' => (int) ($row['is_admin'] ?? 0) === 1, 'avatar' => $row['avatar'] ?? null,
        'bio' => $row['bio'] ?? '', 'referralCode' => $row['referral_code'] ?? '',
        'initials' => strtoupper(substr(implode('', array_map(fn($part) => $part[0] ?? '', preg_split('/\s+/', $row['name']))), 0, 2)),
    ];
}
function requireAdmin(array $current): void {
    if ((int) ($current['is_admin'] ?? 0) !== 1) response(['error' => 'Administrator access required'], 403);
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
        $referralCode = strtoupper(trim((string) ($body['referralCode'] ?? '')));
        $referredBy = null;
        if ($referralCode !== '') {
            $refStmt = $pdo->prepare('SELECT id FROM users WHERE referral_code = ?');
            $refStmt->execute([$referralCode]);
            $referralRow = $refStmt->fetch();
            if ($referralRow) $referredBy = (int) $referralRow['id'];
        }
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, account_id, uid, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $accountId = (string) random_int(1000000, 9999999);
        $uid = strtoupper(bin2hex(random_bytes(6)));
        $ownReferralCode = 'ASIC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        try { $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $accountId, $uid, $ownReferralCode, $referredBy]); }
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
        $bio = trim((string) ($body['bio'] ?? ($current['bio'] ?? '')));
        try { $pdo->prepare('UPDATE users SET name = ?, email = ?, bio = ? WHERE id = ?')->execute([$name, $email, $bio, $current['id']]); }
        catch (PDOException $error) { response(['error' => 'Email already exists'], 409); }
        $current['name'] = $name; $current['email'] = $email; response(['user' => publicUser($current)]);
    }
    if ($parts === ['me', 'password'] && $method === 'PATCH') {
        if (!password_verify((string) ($body['currentPassword'] ?? ''), $current['password_hash'])) response(['error' => 'Current password is incorrect'], 422);
        if (strlen((string) ($body['newPassword'] ?? '')) < 6) response(['error' => 'New password must be at least six characters'], 422);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($body['newPassword'], PASSWORD_DEFAULT), $current['id']]);
        response(['ok' => true]);
    }
    if ($parts === ['me', 'avatar'] && $method === 'POST') {
        if (empty($_FILES['avatar'])) response(['error' => 'Avatar file is required'], 422);
        $file = $_FILES['avatar'];
        if (!empty($file['error']) || $file['size'] > 5 * 1024 * 1024) response(['error' => 'Invalid or oversized avatar'], 422);
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) response(['error' => 'Avatar must be JPG, PNG or WebP'], 422);
        $extension = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'][$mime];
        $directory = __DIR__ . '/uploads';
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) response(['error' => 'Could not create upload directory'], 500);
        $filename = 'avatar-' . $current['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) response(['error' => 'Could not save avatar'], 500);
        $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute(['/uploads/' . $filename, $current['id']]);
        $current['avatar'] = '/uploads/' . $filename;
        response(['user' => publicUser($current)]);
    }
    if ($parts === ['wallets'] && $method === 'GET') {
        $stmt = $pdo->prepare('SELECT coin,address,updated_at FROM wallet_addresses WHERE user_id=? ORDER BY coin');
        $stmt->execute([$current['id']]);
        response($stmt->fetchAll());
    }
    if ($parts === ['wallets'] && $method === 'POST') {
        $coin = strtoupper(trim((string) ($body['coin'] ?? '')));
        $address = trim((string) ($body['address'] ?? ''));
        if (!array_key_exists($coin, coinConfig()) || strlen($address) < 8) response(['error' => 'Valid coin and wallet address are required'], 422);
        $stmt = $pdo->prepare('SELECT id FROM wallet_addresses WHERE user_id=? AND coin=?');
        $stmt->execute([$current['id'],$coin]);
        if ($stmt->fetch()) {
            $pdo->prepare('UPDATE wallet_addresses SET address=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND coin=?')->execute([$address,$current['id'],$coin]);
        } else {
            $pdo->prepare('INSERT INTO wallet_addresses (user_id,coin,address) VALUES (?,?,?)')->execute([$current['id'],$coin,$address]);
        }
        response(['ok' => true]);
    }
    if ($parts === ['dashboard']) {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(p.hashrate),0) hashrate, COALESCE(SUM(p.daily_profit_usd),0) daily, COALESCE(SUM(p.total_return_usd),0) total FROM purchases o JOIN products p ON p.id=o.product_id WHERE o.user_id=? AND o.status="active"');
        $stmt->execute([$current['id']]); $stats = $stmt->fetch();
        response(['balance' => (float) $current['balance'], 'hashrate' => (float) $stats['hashrate'], 'dailyProfit' => (float) $stats['daily'], 'totalProfit' => (float) $stats['total']]);
    }
    if ($parts === ['referrals']) {
        $stmt = $pdo->prepare('SELECT u.id,u.name,u.email,u.created_at,rr.amount_usd,rr.status FROM users u LEFT JOIN referral_rewards rr ON rr.referred_id=u.id AND rr.referrer_id=? WHERE u.referred_by=? ORDER BY u.id DESC');
        $stmt->execute([$current['id'],$current['id']]);
        $users = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_usd),0) AS total FROM referral_rewards WHERE referrer_id=?');
        $stmt->execute([$current['id']]);
        $total = (float) $stmt->fetch()['total'];
        response(['code' => $current['referral_code'] ?? '', 'totalEarned' => $total, 'users' => $users]);
    }
    if ($parts === ['withdrawals'] && $method === 'GET') {
        $stmt=$pdo->prepare('SELECT * FROM withdrawals WHERE user_id=? ORDER BY id DESC');
        $stmt->execute([$current['id']]);
        response($stmt->fetchAll());
    }
    if ($parts === ['withdrawals'] && $method === 'POST') {
        $coin=strtoupper(trim((string)($body['coin']??'')));
        $amount=(float)($body['amountUsd']??0);
        $address=trim((string)($body['address']??''));
        $coins=coinConfig();
        if (!array_key_exists($coin,$coins) || $amount < 10) response(['error'=>'Minimum withdrawal is $10 and coin must be valid'],422);
        if ($amount > (float)$current['balance']) response(['error'=>'Insufficient balance'],422);
        if ($address==='') {
            $wallet=$pdo->prepare('SELECT address FROM wallet_addresses WHERE user_id=? AND coin=?');
            $wallet->execute([$current['id'],$coin]);
            $address=(string)$wallet->fetchColumn();
        }
        if ($address==='') response(['error'=>'Configure a '.$coin.' wallet address first'],422);
        $coinAmount=round($amount/$coins[$coin]['rate'],8);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET balance=balance-? WHERE id=?')->execute([$amount,$current['id']]);
        $pdo->prepare('INSERT INTO withdrawals (user_id,coin,address,amount_usd,amount_coin,status) VALUES (?,?,?,?,?,\'pending\')')->execute([$current['id'],$coin,$address,$amount,$coinAmount]);
        $withdrawalId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$current['id'],'Withdrawal',-$amount,'pending','WD-'.$withdrawalId]);
        $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$current['id'],'Withdrawal submitted','Your withdrawal is pending administrator review.']);
        $pdo->commit();
        response(['id'=>$withdrawalId,'status'=>'pending','coin'=>$coin,'amountUsd'=>$amount,'amountCoin'=>$coinAmount],201);
    }
    if ($parts === ['miners'] || $parts === ['products']) response(productRows($pdo->query('SELECT * FROM products WHERE active=1 ORDER BY id')->fetchAll()));
    if ($parts === ['coins'] && $method === 'GET') {
        $coins=[];
        foreach(coinConfig() as $code=>$coin) $coins[]=['code'=>$code,'name'=>$coin['name'],'symbol'=>$coin['symbol'],'rate'=>$coin['rate']];
        response($coins);
    }
    if ($parts === ['contracts']) {
        $stmt = $pdo->prepare('SELECT o.*, p.name, p.hashrate, p.price_usd price, p.total_return_usd total, p.daily_profit_usd daily, p.icon FROM purchases o JOIN products p ON p.id=o.product_id WHERE o.user_id=? ORDER BY o.id DESC');
        $stmt->execute([$current['id']]); response(array_map(fn($row) => ['id'=>(int)$row['id'], 'name'=>$row['name'], 'hashrate'=>(float)$row['hashrate'], 'price'=>(float)$row['price'], 'total'=>(float)$row['total'], 'daily'=>(float)$row['daily'], 'icon'=>$row['icon'], 'status'=>$row['status'], 'earningsPaid'=>(float)$row['earnings_paid'], 'purchasedAt'=>$row['purchased_at']], $stmt->fetchAll()));
    }
    if ($parts === ['transactions']) {
        $transactions=[];
        $stmt=$pdo->prepare('SELECT id,type,amount,status,reference,created_at date FROM transactions WHERE user_id=? ORDER BY id DESC');
        $stmt->execute([$current['id']]);
        foreach($stmt->fetchAll() as $row) $transactions[]=['id'=>'TX-'.$row['id'],'type'=>$row['type'],'amount'=>(float)$row['amount'],'status'=>$row['status'],'reference'=>$row['reference'],'coin'=>null,'date'=>$row['date']];
        $stmt=$pdo->prepare('SELECT id,invoice_code,network,amount_usd,status,txid,created_at FROM payments WHERE user_id=? ORDER BY id DESC');
        $stmt->execute([$current['id']]);
        foreach($stmt->fetchAll() as $row) $transactions[]=['id'=>'PAY-'.$row['id'],'type'=>'Crypto deposit','amount'=>(float)$row['amount_usd'],'status'=>$row['status'],'reference'=>$row['invoice_code'],'coin'=>$row['network'],'date'=>$row['created_at']];
        $stmt=$pdo->prepare('SELECT id,coin,address,amount_usd,status,txid,created_at FROM withdrawals WHERE user_id=? ORDER BY id DESC');
        $stmt->execute([$current['id']]);
        foreach($stmt->fetchAll() as $row) $transactions[]=['id'=>'WD-'.$row['id'],'type'=>'Crypto withdrawal','amount'=>(float)$row['amount_usd'],'status'=>$row['status'],'reference'=>$row['txid'] ?: 'WD-'.$row['id'],'coin'=>$row['coin'],'date'=>$row['created_at']];
        usort($transactions, fn($a,$b)=>strcmp($b['date'],$a['date']));
        response($transactions);
    }
    if ($parts === ['notifications']) { $stmt=$pdo->prepare('SELECT id,title,message,created_at date,read_at FROM notifications WHERE user_id=? ORDER BY id DESC'); $stmt->execute([$current['id']]); response($stmt->fetchAll()); }
    if ($parts === ['payments', 'invoice'] && $method === 'POST') {
        $item = product((int) ($body['productId'] ?? 0)); if (!$item) response(['error'=>'Product not found'],404);
        $network = strtoupper(trim((string) ($body['network'] ?? 'BTC')));
        $coins = coinConfig();
        if (!array_key_exists($network,$coins)) response(['error'=>'Payment coin is not supported'],422);
        $coin = $coins[$network];
        $cryptoAmount = round((float) $item['price_usd'] / (float) $coin['rate'], 8);
        $code = 'INV-' . strtoupper(bin2hex(random_bytes(5)));
        $address = $coin['address'];
        $pdo->prepare('INSERT INTO payments (user_id,product_id,invoice_code,network,address,amount_usd,amount_crypto,status) VALUES (?,?,?,?,?,?,?,\'pending\')')->execute([$current['id'],$item['id'],$code,$network,$address,$item['price_usd'],$cryptoAmount]);
        $paymentId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$current['id'],'Crypto deposit', (float)$item['price_usd'],'pending',$code]);
        $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$current['id'],'Deposit pending','Your '.$network.' deposit is awaiting confirmation.']);
        response(['id'=>$paymentId,'invoiceCode'=>$code,'network'=>$network,'address'=>$address,'amountUsd'=>(float)$item['price_usd'],'amountCrypto'=>$cryptoAmount,'status'=>'pending','product'=>productRows([$item])[0]],201);
    }
    if (count($parts) === 3 && $parts[0] === 'payments' && $parts[1] === 'invoice' && $method === 'GET') {
        $stmt=$pdo->prepare('SELECT pay.*, p.name product_name FROM payments pay JOIN products p ON p.id=pay.product_id WHERE pay.id=? AND pay.user_id=?'); $stmt->execute([(int)$parts[2],$current['id']]); $payment=$stmt->fetch(); if(!$payment) response(['error'=>'Invoice not found'],404); response($payment);
    }
    if ($parts === ['admin', 'overview']) {
        requireAdmin($current);
        $users=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $pendingPayments=(float)$pdo->query("SELECT COALESCE(SUM(amount_usd),0) FROM payments WHERE status='pending'")->fetchColumn();
        $paidPayments=(float)$pdo->query("SELECT COALESCE(SUM(amount_usd),0) FROM payments WHERE status='paid'")->fetchColumn();
        $pendingWithdrawals=(float)$pdo->query("SELECT COALESCE(SUM(amount_usd),0) FROM withdrawals WHERE status='pending'")->fetchColumn();
        $purchases=(int)$pdo->query('SELECT COUNT(*) FROM purchases')->fetchColumn();
        response(['users'=>$users,'pendingPayments'=>$pendingPayments,'paidPayments'=>$paidPayments,'pendingWithdrawals'=>$pendingWithdrawals,'purchases'=>$purchases]);
    }
    if ($parts === ['admin', 'users']) {
        requireAdmin($current);
        $stmt=$pdo->query('SELECT u.*, (SELECT COALESCE(SUM(amount_usd),0) FROM payments p WHERE p.user_id=u.id AND p.status="paid") AS deposited, (SELECT COUNT(*) FROM purchases pu WHERE pu.user_id=u.id) AS owned FROM users u ORDER BY u.id DESC');
        $rows=$stmt->fetchAll();
        response(array_map(fn($row)=>['id'=>(int)$row['id'],'name'=>$row['name'],'email'=>$row['email'],'accountId'=>$row['account_id'],'uid'=>$row['uid'],'balance'=>(float)$row['balance'],'isAdmin'=>(int)$row['is_admin'],'avatar'=>$row['avatar'],'bio'=>$row['bio'],'referralCode'=>$row['referral_code'],'deposited'=>(float)$row['deposited'],'owned'=>(int)$row['owned'],'createdAt'=>$row['created_at']],$rows));
    }
    if ($parts === ['admin', 'payments']) {
        requireAdmin($current);
        $stmt=$pdo->query('SELECT p.*,u.name,u.email,pr.name AS product_name FROM payments p JOIN users u ON u.id=p.user_id JOIN products pr ON pr.id=p.product_id ORDER BY p.id DESC');
        response($stmt->fetchAll());
    }
    if ($parts === ['admin', 'withdrawals']) {
        requireAdmin($current);
        $stmt=$pdo->query('SELECT w.*,u.name,u.email FROM withdrawals w JOIN users u ON u.id=w.user_id ORDER BY w.id DESC');
        response($stmt->fetchAll());
    }
    if ($parts === ['admin', 'products']) {
        requireAdmin($current);
        if ($method === 'GET') response($pdo->query('SELECT * FROM products ORDER BY id')->fetchAll());
        if ($method === 'POST') {
            $stmt=$pdo->prepare('INSERT INTO products (name,price_usd,total_return_usd,daily_profit_usd,hashrate,icon,active) VALUES (?,?,?,?,?,?,1)');
            $stmt->execute([(string)($body['name']??''),(float)($body['price']??0),(float)($body['total']??0),(float)($body['daily']??0),(float)($body['hashrate']??0),(string)($body['icon']??'▧')]);
            response(['id'=>(int)$pdo->lastInsertId(),'ok'=>true],201);
        }
        if ($method === 'PATCH') {
            $id=(int)($body['id']??0);
            $stmt=$pdo->prepare('UPDATE products SET name=?,price_usd=?,total_return_usd=?,daily_profit_usd=?,hashrate=?,icon=?,active=? WHERE id=?');
            $stmt->execute([(string)($body['name']??''),(float)($body['price']??0),(float)($body['total']??0),(float)($body['daily']??0),(float)($body['hashrate']??0),(string)($body['icon']??'▧'),(int)($body['active']??1),$id]);
            response(['ok'=>true]);
        }
    }
    if (count($parts) === 4 && $parts[0] === 'admin' && $parts[1] === 'payments' && $method === 'POST') {
        requireAdmin($current);
        $paymentId=(int)$parts[2]; $action=$parts[3];
        $stmt=$pdo->prepare('SELECT * FROM payments WHERE id=?'); $stmt->execute([$paymentId]); $payment=$stmt->fetch();
        if (!$payment) response(['error'=>'Payment not found'],404);
        if ($payment['status'] !== 'pending') response(['error'=>'Payment has already been processed'],422);
        if ($action === 'confirm') {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE payments SET status="paid",txid=?,paid_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(string)($body['txid']??''),$paymentId]);
            $pdo->prepare('INSERT INTO purchases (user_id,product_id,payment_id,status) VALUES (?,?,?,\'active\')')->execute([$payment['user_id'],$payment['product_id'],$paymentId]);
            $pdo->prepare('UPDATE transactions SET status="completed" WHERE user_id=? AND reference=?')->execute([$payment['user_id'],$payment['invoice_code']]);
            $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$payment['user_id'],'Payment confirmed','Your mining product is now active.']);
            $ref=$pdo->prepare('SELECT referred_by FROM users WHERE id=?'); $ref->execute([$payment['user_id']]); $referrerId=(int)$ref->fetchColumn();
            if ($referrerId) {
                $reward=round((float)$payment['amount_usd']*0.05,8);
                $pdo->prepare('INSERT INTO referral_rewards (referrer_id,referred_id,amount_usd,status) VALUES (?,?,?,\'paid\')')->execute([$referrerId,$payment['user_id'],$reward]);
                $pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$reward,$referrerId]);
                $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$referrerId,'Referral reward',$reward,'completed','REF-'.$payment['invoice_code']]);
            }
            $pdo->commit();
            response(['ok'=>true,'status'=>'paid']);
        }
        if ($action === 'cancel') {
            $pdo->prepare('UPDATE payments SET status="cancelled",txid=? WHERE id=?')->execute([(string)($body['txid']??''),$paymentId]);
            $pdo->prepare('UPDATE transactions SET status="cancelled" WHERE user_id=? AND reference=?')->execute([$payment['user_id'],$payment['invoice_code']]);
            $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$payment['user_id'],'Deposit cancelled','Your pending deposit was cancelled by the administrator.']);
            response(['ok'=>true,'status'=>'cancelled']);
        }
        response(['error'=>'Invalid action'],422);
    }
    if (count($parts) === 4 && $parts[0] === 'admin' && $parts[1] === 'withdrawals' && $method === 'POST') {
        requireAdmin($current);
        $withdrawalId=(int)$parts[2]; $action=$parts[3];
        $stmt=$pdo->prepare('SELECT * FROM withdrawals WHERE id=?'); $stmt->execute([$withdrawalId]); $withdrawal=$stmt->fetch();
        if (!$withdrawal) response(['error'=>'Withdrawal not found'],404);
        if ($withdrawal['status'] !== 'pending') response(['error'=>'Withdrawal has already been processed'],422);
        if ($action === 'approve') {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE withdrawals SET status="completed",txid=?,admin_note=?,processed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(string)($body['txid']??''),(string)($body['note']??''),$withdrawalId]);
            $pdo->prepare('UPDATE transactions SET status="completed",reference=? WHERE user_id=? AND reference=?')->execute([(string)($body['txid']??'WD-'.$withdrawalId),$withdrawal['user_id'],'WD-'.$withdrawalId]);
            $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$withdrawal['user_id'],'Withdrawal approved','Your withdrawal was approved and processed.']);
            $pdo->commit();
            response(['ok'=>true,'status'=>'completed']);
        }
        if ($action === 'reject') {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE withdrawals SET status="cancelled",admin_note=?,processed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(string)($body['note']??'Rejected by administrator'),$withdrawalId]);
            $pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([(float)$withdrawal['amount_usd'],$withdrawal['user_id']]);
            $pdo->prepare('UPDATE transactions SET status="cancelled" WHERE user_id=? AND reference=?')->execute([$withdrawal['user_id'],'WD-'.$withdrawalId]);
            $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$withdrawal['user_id'],'Withdrawal refunded',$withdrawal['amount_usd'],'completed','WDR-'.$withdrawalId]);
            $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$withdrawal['user_id'],'Withdrawal cancelled','Your withdrawal was cancelled and funds were restored.']);
            $pdo->commit();
            response(['ok'=>true,'status'=>'cancelled']);
        }
        response(['error'=>'Invalid action'],422);
    }
    if ($parts === ['payments', 'webhook'] && $method === 'POST') {
        $secret = config('PAYMENT_WEBHOOK_SECRET'); $raw=file_get_contents('php://input'); $signature=$_SERVER['HTTP_X_PAYMENT_SIGNATURE'] ?? '';
        if ($secret !== '' && !hash_equals(hash_hmac('sha256',$raw,$secret),$signature)) response(['error'=>'Invalid webhook signature'],401);
        $payload=json_decode($raw,true) ?: $body; $stmt=$pdo->prepare('SELECT * FROM payments WHERE invoice_code=?'); $stmt->execute([(string)($payload['invoiceCode']??'')]); $payment=$stmt->fetch(); if(!$payment) response(['error'=>'Invoice not found'],404);
        if ($payment['status'] !== 'paid') {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE payments SET status="paid", txid=?, paid_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(string)($payload['txid']??''),$payment['id']]);
            $pdo->prepare('INSERT INTO purchases (user_id,product_id,payment_id,status) VALUES (?,?,?,\'active\')')->execute([$payment['user_id'],$payment['product_id'],$payment['id']]);
            $pdo->prepare('UPDATE transactions SET status="completed" WHERE user_id=? AND reference=?')->execute([$payment['user_id'],$payment['invoice_code']]);
            $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$payment['user_id'],'Payment confirmed','Your mining product is now active.']);
            $ref=$pdo->prepare('SELECT referred_by FROM users WHERE id=?'); $ref->execute([$payment['user_id']]); $referrerId=(int)$ref->fetchColumn();
            if ($referrerId) {
                $reward=round((float)$payment['amount_usd']*0.05,8);
                $pdo->prepare('INSERT INTO referral_rewards (referrer_id,referred_id,amount_usd,status) VALUES (?,?,?,\'paid\')')->execute([$referrerId,$payment['user_id'],$reward]);
                $pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$reward,$referrerId]);
                $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$referrerId,'Referral reward',$reward,'completed','REF-'.$payment['invoice_code']]);
            }
            $pdo->commit();
        }
        response(['ok'=>true,'status'=>'paid']);
    }
    response(['error'=>'Not found'],404);
} catch (Throwable $error) {
    response(['error' => 'Server error', 'detail' => config('APP_DEBUG') === 'true' ? $error->getMessage() : null], 500);
}
