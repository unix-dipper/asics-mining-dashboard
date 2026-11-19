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
    recordLoginDevice($current);
    return $current;
}
function publicUser(array $row): array {
    return [
        'id' => (int) $row['id'], 'name' => $row['name'], 'email' => $row['email'],
        'accountId' => $row['account_id'], 'uid' => $row['uid'], 'balance' => (float) $row['balance'],
        'isAdmin' => (int) ($row['is_admin'] ?? 0) === 1, 'avatar' => $row['avatar'] ?? null,
        'bio' => $row['bio'] ?? '', 'referralCode' => $row['referral_code'] ?? '',
        'vipUntil' => $row['vip_until'] ?? null, 'status' => $row['status'] ?? 'active',
        'initials' => strtoupper(substr(implode('', array_map(fn($part) => $part[0] ?? '', preg_split('/\s+/', $row['name']))), 0, 2)),
    ];
}
function requireAdmin(array $current): void {
    if ((int) ($current['is_admin'] ?? 0) !== 1) response(['error' => 'Administrator access required'], 403);
}
function clientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        $value=trim((string)($_SERVER[$key] ?? ''));
        if ($value !== '') return trim(explode(',',$value)[0]);
    }
    return 'unknown';
}
function recordLoginDevice(array $user): void {
    $pdo=db(); $ip=clientIp(); $agent=substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),0,500);
    $stmt=$pdo->prepare('SELECT id FROM login_devices WHERE user_id=? AND ip_address=? AND user_agent=?');
    $stmt->execute([(int)$user['id'],$ip,$agent]);
    if ($stmt->fetch()) {
        $pdo->prepare('UPDATE login_devices SET last_seen=CURRENT_TIMESTAMP WHERE user_id=? AND ip_address=? AND user_agent=?')->execute([(int)$user['id'],$ip,$agent]);
    } else {
        $pdo->prepare('INSERT INTO login_devices (user_id,ip_address,user_agent) VALUES (?,?,?)')->execute([(int)$user['id'],$ip,$agent]);
    }
}
function referralRate(PDO $pdo, int $referrerId): float {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM users WHERE referred_by=?');
    $stmt->execute([$referrerId]);
    $count=(int)$stmt->fetchColumn();
    if ($count >= 10) return 0.10;
    if ($count >= 5) return 0.07;
    return 0.05;
}
function sendTelegram(string $message): bool {
    $token=config('TELEGRAM_BOT_TOKEN'); $chatId=config('TELEGRAM_CHAT_ID');
    if ($token==='' || $chatId==='') return false;
    $url='https://api.telegram.org/bot'.$token.'/sendMessage';
    $payload=['chat_id'=>$chatId,'text'=>$message,'parse_mode'=>'HTML','disable_web_page_preview'=>true];
    if (function_exists('curl_init')) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8]);
        $result=curl_exec($ch); curl_close($ch);
        $data=json_decode((string)$result,true);
        return is_array($data)&&($data['ok']??false);
    }
    return false;
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
        'image' => ['/images/dr7.svg','/images/ka3.svg','/images/s23.svg','/images/s21.svg'][$row['id'] - 1] ?? '/images/miner.svg',
        'vipOnly' => (int)($row['vip_only'] ?? 0) === 1,
    ], $rows);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$parts = pathParts();
$pdo = db();
$body = in_array($method, ['POST', 'PATCH'], true) ? jsonBody() : [];

try {
    if ($parts === ['public', 'products'] && $method === 'GET') {
        $rows = $pdo->query('SELECT * FROM products WHERE active=1 AND vip_only=0 ORDER BY id')->fetchAll();
        response(productRows($rows));
    }
    if ($parts === ['coins'] && $method === 'GET') {
        $coins=[];
        foreach(coinConfig() as $code=>$coin) $coins[]=['code'=>$code,'name'=>$coin['name'],'symbol'=>$coin['symbol'],'rate'=>$coin['rate']];
        response($coins);
    }
    if ($parts === ['contact'] && $method === 'POST') {
        $name=trim((string)($body['name']??'')); $email=trim((string)($body['email']??'')); $message=trim((string)($body['message']??''));
        if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($message)<5) response(['error'=>'Please provide your name, a valid email and a message'],422);
        $pdo->prepare('INSERT INTO contact_messages (name,email,message) VALUES (?,?,?)')->execute([$name,$email,$message]);
        sendTelegram("📩 <b>New contact message</b>\nName: ".htmlspecialchars($name)."\nEmail: ".htmlspecialchars($email)."\nMessage: ".htmlspecialchars($message));
        response(['ok'=>true],201);
    }
    if ($parts === ['auth', 'login'] && $method === 'POST') {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([trim((string) ($body['email'] ?? ''))]);
        $found = $stmt->fetch();
        if (!$found || !password_verify((string) ($body['password'] ?? ''), $found['password_hash'])) response(['error' => 'Invalid credentials'], 401);
        if (($found['status'] ?? 'active') === 'suspended') response(['error' => 'This account has been suspended'],403);
        $_SESSION['user_id'] = (int) $found['id'];
        recordLoginDevice($found);
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
        recordLoginDevice($pdo->query('SELECT * FROM users WHERE id='.(int)$userId)->fetch());
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
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(p.hashrate),0) hashrate, COALESCE(SUM(p.daily_profit_usd),0) daily, COALESCE(SUM(p.total_return_usd),0) total FROM purchases o JOIN products p ON p.id=o.product_id WHERE o.user_id=? AND o.status="active" AND (o.expires_at IS NULL OR o.expires_at > CURRENT_TIMESTAMP)');
        $stmt->execute([$current['id']]); $stats = $stmt->fetch();
        response(['balance' => (float) $current['balance'], 'hashrate' => (float) $stats['hashrate'], 'dailyProfit' => (float) $stats['daily'], 'totalProfit' => (float) $stats['total']]);
    }
    if ($parts === ['vip', 'status']) {
        $vipActive=!empty($current['vip_until']) && strtotime((string)$current['vip_until'])>time();
        $subscription=$pdo->prepare("SELECT COUNT(*) FROM payments p JOIN products pr ON pr.id=p.product_id WHERE p.user_id=? AND pr.vip_only=1 AND p.status IN ('pending','paid')");
        $subscription->execute([(int)$current['id']]);
        $subscribedOnce=(int)$subscription->fetchColumn()>0;
        $today=(string)date('Y-m-d');
        $claim=$pdo->prepare('SELECT id FROM vip_free_claims WHERE user_id=? AND activation_date=?');
        $claim->execute([(int)$current['id'],$today]);
        $month=(string)date('Y-m');
        $count=$pdo->prepare("SELECT COUNT(*) FROM vip_free_claims WHERE user_id=? AND substr(activation_date,1,7)=?");
        $count->execute([(int)$current['id'],$month]);
        $monthlyCount=(int)$count->fetchColumn();
        $reward=$pdo->prepare('SELECT id FROM vip_month_rewards WHERE user_id=? AND reward_month=?');
        $reward->execute([(int)$current['id'],$month]);
        response(['active'=>$vipActive,'until'=>$current['vip_until']??null,'subscribedOnce'=>$subscribedOnce,'price'=>9.99,'claimedToday'=>(bool)$claim->fetch(),'monthlyActivations'=>$monthlyCount,'monthlyReward'=>5,'rewardClaimed'=>(bool)$reward->fetch()]);
    }
    if ($parts === ['vip', 'subscribe'] && $method === 'POST') {
        $subscription=$pdo->prepare("SELECT COUNT(*) FROM payments p JOIN products pr ON pr.id=p.product_id WHERE p.user_id=? AND pr.vip_only=1 AND p.status IN ('pending','paid')");
        $subscription->execute([(int)$current['id']]);
        if ((int)$subscription->fetchColumn()>0) {
            response(['error'=>'Your VIP subscription can only be purchased once per account.'],422);
        }
        $stmt=$pdo->prepare("SELECT * FROM products WHERE name='VIP Daily Miner' OR name LIKE 'VIP Black Diamond%' ORDER BY id LIMIT 1");
        $stmt->execute(); $item=$stmt->fetch();
        if (!$item) {
            $pdo->prepare('INSERT INTO products (name,price_usd,total_return_usd,daily_profit_usd,hashrate,icon,active,vip_only) VALUES (?,?,?,?,?,?,1,1)')
                ->execute(['VIP Daily Miner',9.99,5,0.1667,95,'◆']);
            $id=(int)$pdo->lastInsertId();
            $stmt=$pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$id]); $item=$stmt->fetch();
        }
        $network=strtoupper(trim((string)($body['network']??'BTC')));
        $coins=coinConfig(); if(!array_key_exists($network,$coins)) response(['error'=>'Payment coin is not supported'],422);
        $cryptoAmount=round(9.99/(float)$coins[$network]['rate'],8);
        $code='VIP-'.strtoupper(bin2hex(random_bytes(5)));
        $pdo->prepare('INSERT INTO payments (user_id,product_id,invoice_code,network,address,amount_usd,amount_crypto,status) VALUES (?,?,?,?,?,?,?,\'pending\')')
            ->execute([$current['id'],$item['id'],$code,$network,$coins[$network]['address'],9.99,$cryptoAmount]);
        $paymentId=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$current['id'],'VIP subscription',9.99,'pending',$code]);
        response(['id'=>$paymentId,'invoiceCode'=>$code,'network'=>$network,'address'=>$coins[$network]['address'],'amountUsd'=>9.99,'amountCrypto'=>$cryptoAmount,'status'=>'pending','product'=>['name'=>'VIP Subscription','price'=>9.99]],201);
    }
    if ($parts === ['vip', 'activate'] && $method === 'POST') {
        $vipActive=!empty($current['vip_until']) && strtotime((string)$current['vip_until'])>time();
        if (!$vipActive) response(['error'=>'A VIP subscription is required'],403);
        $today=(string)date('Y-m-d');
        $stmt=$pdo->prepare('SELECT id FROM vip_free_claims WHERE user_id=? AND activation_date=?');
        $stmt->execute([(int)$current['id'],$today]);
        if ($stmt->fetch()) response(['error'=>'Free machine was already activated today'],422);
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO vip_free_claims (user_id,activation_date) VALUES (?,?)')->execute([(int)$current['id'],$today]);
        $product=$pdo->query("SELECT id FROM products WHERE name='VIP Daily Miner' LIMIT 1")->fetch();
        if (!$product) {
            $pdo->prepare('INSERT INTO products (name,price_usd,total_return_usd,daily_profit_usd,hashrate,icon,active,vip_only) VALUES (?,?,?,?,?,?,1,1)')
                ->execute(['VIP Daily Miner',9.99,5,0.1667,95,'◆']);
            $productId=(int)$pdo->lastInsertId();
        } else $productId=(int)$product['id'];
        $expires=(string)date('Y-m-d H:i:s',time()+86400);
        $pdo->prepare('INSERT INTO purchases (user_id,product_id,status,expires_at) VALUES (?,?,\'active\',?)')->execute([(int)$current['id'],$productId,$expires]);
        $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([(int)$current['id'],'VIP free machine activation',0,'completed','VIP-'.$today]);

        $month=(string)date('Y-m');
        $count=$pdo->prepare("SELECT COUNT(*) FROM vip_free_claims WHERE user_id=? AND substr(activation_date,1,7)=?");
        $count->execute([(int)$current['id'],$month]); $monthlyCount=(int)$count->fetchColumn();
        $reward=$pdo->prepare('SELECT id FROM vip_month_rewards WHERE user_id=? AND reward_month=?');
        $reward->execute([(int)$current['id'],$month]); $rewardClaimed=(bool)$reward->fetch();
        $monthlyPayout=false;
        if ($monthlyCount >= 30 && !$rewardClaimed) {
            $pdo->prepare('INSERT INTO vip_month_rewards (user_id,reward_month,amount) VALUES (?,?,5)')->execute([(int)$current['id'],$month]);
            $pdo->prepare('UPDATE users SET balance=balance+5 WHERE id=?')->execute([(int)$current['id']]);
            $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([(int)$current['id'],'VIP 30-day reward',5,'completed','VIP-M-'.$month]);
            $monthlyPayout=true;
        }
        $pdo->commit();
        response(['ok'=>true,'expiresAt'=>$expires,'monthlyActivations'=>$monthlyCount,'monthlyPayout'=>$monthlyPayout]);
    }
    if ($parts === ['referrals']) {
        $stmt = $pdo->prepare('SELECT u.id,u.name,u.email,u.created_at,rr.amount_usd,rr.status FROM users u LEFT JOIN referral_rewards rr ON rr.referred_id=u.id AND rr.referrer_id=? WHERE u.referred_by=? ORDER BY u.id DESC');
        $stmt->execute([$current['id'],$current['id']]);
        $users = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_usd),0) AS total FROM referral_rewards WHERE referrer_id=?');
        $stmt->execute([$current['id']]);
        $total = (float) $stmt->fetch()['total'];
        $count=(int)count($users);
        $tier=$count>=10?'Ambassador':($count>=5?'Builder':'Starter');
        $rate=$count>=10?10:($count>=5?7:5);
        response(['code' => $current['referral_code'] ?? '', 'totalEarned' => $total, 'users' => $users, 'tier'=>$tier, 'rate'=>$rate, 'tiers'=>[['name'=>'Starter','min'=>1,'max'=>4,'rate'=>5],['name'=>'Builder','min'=>5,'max'=>9,'rate'=>7],['name'=>'Ambassador','min'=>10,'max'=>null,'rate'=>10]]]);
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
    if ($parts === ['miners'] || $parts === ['products']) {
        $vipActive = !empty($current['vip_until']) && strtotime((string)$current['vip_until']) > time();
        $query = $vipActive ? 'SELECT * FROM products WHERE active=1 ORDER BY id' : 'SELECT * FROM products WHERE active=1 AND vip_only=0 ORDER BY id';
        response(productRows($pdo->query($query)->fetchAll()));
    }
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
        $stmt=$pdo->prepare('SELECT id,invoice_code,network,amount_usd,status,txid,created_at,confirmed_at,paid_at,cancelled_at FROM payments WHERE user_id=? ORDER BY id DESC');
        $stmt->execute([$current['id']]);
        foreach($stmt->fetchAll() as $row) $transactions[]=['id'=>'PAY-'.$row['id'],'type'=>'Crypto deposit','amount'=>(float)$row['amount_usd'],'status'=>$row['status'],'reference'=>$row['invoice_code'],'coin'=>$row['network'],'date'=>$row['created_at'],'confirmedAt'=>$row['confirmed_at']??null,'paidAt'=>$row['paid_at']??null,'cancelledAt'=>$row['cancelled_at']??null];
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
    if (count($parts)===3 && $parts[0]==='payments' && $parts[1]==='confirm' && $method==='POST') {
        $paymentId=(int)$parts[2];
        $stmt=$pdo->prepare('SELECT pay.*,p.name AS product_name,u.name AS user_name,u.email AS user_email FROM payments pay JOIN products p ON p.id=pay.product_id JOIN users u ON u.id=pay.user_id WHERE pay.id=? AND pay.user_id=?');
        $stmt->execute([$paymentId,$current['id']]); $payment=$stmt->fetch();
        if(!$payment) response(['error'=>'Invoice not found'],404);
        if($payment['status']!=='pending') response(['error'=>'Payment is no longer pending'],422);
        $pdo->prepare('UPDATE payments SET status="review",confirmed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$paymentId]);
        $pdo->prepare('UPDATE transactions SET status="pending review" WHERE user_id=? AND reference=?')->execute([$current['id'],$payment['invoice_code']]);
        $pdo->prepare('INSERT INTO payment_events (payment_id,event_type,ip_address,user_agent) VALUES (?,?,?,?)')->execute([$paymentId,'user_confirmed',clientIp(),substr((string)($_SERVER['HTTP_USER_AGENT']??'unknown'),0,500)]);
        $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$current['id'],'Payment confirmation sent','The administrator has been notified to verify your deposit.']);
        sendTelegram("💳 <b>Payment confirmation</b>\nUser: ".htmlspecialchars((string)$payment['user_name'])." (" . htmlspecialchars((string)$payment['user_email']).")\nInvoice: ".$payment['invoice_code']."\nProduct: ".htmlspecialchars((string)$payment['product_name'])."\nNetwork: ".$payment['network']."\nAmount: $".number_format((float)$payment['amount_usd'],2)."\nIP: ".htmlspecialchars(clientIp()));
        response(['ok'=>true,'status'=>'review','confirmedAt'=>date('Y-m-d H:i:s')]);
    }
    if ($parts === ['admin', 'messages']) {
        requireAdmin($current);
        $stmt=$pdo->query('SELECT id,name,email,message,created_at FROM contact_messages ORDER BY id DESC');
        response($stmt->fetchAll());
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
        $stmt=$pdo->query('SELECT u.*, (SELECT COALESCE(SUM(amount_usd),0) FROM payments p WHERE p.user_id=u.id AND p.status="paid") AS deposited, (SELECT COUNT(*) FROM purchases pu WHERE pu.user_id=u.id) AS owned, (SELECT COALESCE(SUM(pr.daily_profit_usd),0) FROM purchases pu JOIN products pr ON pr.id=pu.product_id WHERE pu.user_id=u.id AND pu.status="active") AS earning FROM users u ORDER BY u.id DESC');
        $rows=$stmt->fetchAll();
        $result=[];
        foreach($rows as $row){
            $devices=$pdo->prepare('SELECT ip_address,user_agent,first_seen,last_seen FROM login_devices WHERE user_id=? ORDER BY last_seen DESC');
            $devices->execute([$row['id']]);
            $result[]=['id'=>(int)$row['id'],'name'=>$row['name'],'email'=>$row['email'],'accountId'=>$row['account_id'],'uid'=>$row['uid'],'balance'=>(float)$row['balance'],'isAdmin'=>(int)$row['is_admin'],'status'=>$row['status']??'active','avatar'=>$row['avatar'],'bio'=>$row['bio'],'referralCode'=>$row['referral_code'],'vipUntil'=>$row['vip_until']??null,'deposited'=>(float)$row['deposited'],'owned'=>(int)$row['owned'],'earning'=>(float)$row['earning'],'createdAt'=>$row['created_at'],'devices'=>$devices->fetchAll()];
        }
        response($result);
    }
    if (count($parts)===4 && $parts[0]==='admin' && $parts[1]==='users' && $method==='POST') {
        requireAdmin($current);
        $userId=(int)$parts[2]; $action=$parts[3];
        $stmt=$pdo->prepare('SELECT id,name,status FROM users WHERE id=?'); $stmt->execute([$userId]); $target=$stmt->fetch();
        if(!$target) response(['error'=>'User not found'],404);
        if($action==='suspend') {
            $pdo->prepare('UPDATE users SET status="suspended",admin_note=? WHERE id=?')->execute([(string)($body['note']??'Suspended by administrator'),$userId]);
            $pdo->prepare('INSERT INTO account_audit (user_id,actor_id,action,ip_address,user_agent,details) VALUES (?,?,?,?,?,?)')->execute([$userId,$current['id'],'account_suspended',clientIp(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),(string)($body['note']??'')]);
            response(['ok'=>true,'status'=>'suspended']);
        }
        if($action==='activate') {
            $pdo->prepare('UPDATE users SET status="active",admin_note=? WHERE id=?')->execute(['',(int)$userId]);
            $pdo->prepare('INSERT INTO account_audit (user_id,actor_id,action,ip_address,user_agent,details) VALUES (?,?,?,?,?,?)')->execute([$userId,$current['id'],'account_activated',clientIp(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),'']);
            response(['ok'=>true,'status'=>'active']);
        }
        if($action==='adjust') {
            $amount=(float)($body['amount']??0); $reason=trim((string)($body['reason']??'Balance adjustment'));
            if($amount==0) response(['error'=>'Adjustment amount cannot be zero'],422);
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$amount,$userId]);
            $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$userId,'Admin balance adjustment',$amount,'completed','ADJ-'.$userId.'-'.time()]);
            $pdo->prepare('INSERT INTO account_audit (user_id,actor_id,action,ip_address,user_agent,details) VALUES (?,?,?,?,?,?)')->execute([$userId,$current['id'],'balance_adjusted',clientIp(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),$reason.' ('.($amount>0?'+':'').$amount.')']);
            $pdo->commit();
            response(['ok'=>true,'adjustment'=>$amount]);
        }
        response(['error'=>'Invalid action'],422);
    }
    if ($parts === ['admin','audit']) {
        requireAdmin($current);
        $stmt=$pdo->query('SELECT a.*,u.name AS user_name,au.name AS actor_name FROM account_audit a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN users au ON au.id=a.actor_id ORDER BY a.id DESC');
        response($stmt->fetchAll());
    }
    if ($parts === ['admin', 'payments']) {
        requireAdmin($current);
        $stmt=$pdo->query('SELECT p.*,u.name,u.email,pr.name AS product_name,(SELECT event_type FROM payment_events pe WHERE pe.payment_id=p.id ORDER BY pe.id DESC LIMIT 1) AS latest_event FROM payments p JOIN users u ON u.id=p.user_id JOIN products pr ON pr.id=p.product_id ORDER BY p.id DESC');
        $rows=$stmt->fetchAll();
        foreach($rows as &$row){
            $events=$pdo->prepare('SELECT event_type,ip_address,user_agent,created_at FROM payment_events WHERE payment_id=? ORDER BY id DESC');
            $events->execute([(int)$row['id']]);
            $row['events']=$events->fetchAll();
        }
        response($rows);
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
            $stmt=$pdo->prepare('INSERT INTO products (name,price_usd,total_return_usd,daily_profit_usd,hashrate,icon,active,vip_only) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([(string)($body['name']??''),(float)($body['price']??0),(float)($body['total']??0),(float)($body['daily']??0),(float)($body['hashrate']??0),(string)($body['icon']??'▧'),(int)($body['active']??1),(int)($body['vipOnly']??0)]);
            response(['id'=>(int)$pdo->lastInsertId(),'ok'=>true],201);
        }
        if ($method === 'PATCH') {
            $id=(int)($body['id']??0);
            $stmt=$pdo->prepare('UPDATE products SET name=?,price_usd=?,total_return_usd=?,daily_profit_usd=?,hashrate=?,icon=?,active=?,vip_only=? WHERE id=?');
            $stmt->execute([(string)($body['name']??''),(float)($body['price']??0),(float)($body['total']??0),(float)($body['daily']??0),(float)($body['hashrate']??0),(string)($body['icon']??'▧'),(int)($body['active']??1),(int)($body['vipOnly']??0),$id]);
            response(['ok'=>true]);
        }
    }
    if (count($parts)===3 && $parts[0]==='admin' && $parts[1]==='products' && $method==='DELETE') {
        requireAdmin($current);
        $id=(int)$parts[2];
        $pdo->prepare('UPDATE products SET active=0 WHERE id=?')->execute([$id]);
        response(['ok'=>true,'active'=>false]);
    }
    if (count($parts) === 4 && $parts[0] === 'admin' && $parts[1] === 'payments' && $method === 'POST') {
        requireAdmin($current);
        $paymentId=(int)$parts[2]; $action=$parts[3];
        $stmt=$pdo->prepare('SELECT * FROM payments WHERE id=?'); $stmt->execute([$paymentId]); $payment=$stmt->fetch();
        if (!$payment) response(['error'=>'Payment not found'],404);
        if (!in_array($payment['status'], ['pending','review'], true)) response(['error'=>'Payment has already been processed'],422);
        if ($action === 'confirm') {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE payments SET status="paid",txid=?,paid_at=CURRENT_TIMESTAMP,confirmed_at=COALESCE(confirmed_at,CURRENT_TIMESTAMP) WHERE id=?')->execute([(string)($body['txid']??''),$paymentId]);
            $pdo->prepare('INSERT INTO payment_events (payment_id,event_type,ip_address,user_agent) VALUES (?,?,?,?)')->execute([$paymentId,'confirmed',clientIp(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
            $product=$pdo->prepare('SELECT vip_only FROM products WHERE id=?'); $product->execute([$payment['product_id']]); $vipOnly=(int)$product->fetchColumn();
            if ($vipOnly) {
                $user=$pdo->prepare('SELECT vip_until FROM users WHERE id=?'); $user->execute([$payment['user_id']]); $until=$user->fetchColumn();
                $base=max(time(),$until ? strtotime((string)$until) : time());
                $vipUntil=date('Y-m-d H:i:s',$base+30*86400);
                $pdo->prepare('UPDATE users SET vip_until=? WHERE id=?')->execute([$vipUntil,$payment['user_id']]);
            } else {
                $pdo->prepare('INSERT INTO purchases (user_id,product_id,payment_id,status) VALUES (?,?,?,\'active\')')->execute([$payment['user_id'],$payment['product_id'],$paymentId]);
            }
            $pdo->prepare('UPDATE transactions SET status="completed" WHERE user_id=? AND reference=?')->execute([$payment['user_id'],$payment['invoice_code']]);
            $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$payment['user_id'],'Payment confirmed','Your mining product is now active.']);
            $ref=$pdo->prepare('SELECT referred_by FROM users WHERE id=?'); $ref->execute([$payment['user_id']]); $referrerId=(int)$ref->fetchColumn();
            if ($referrerId) {
                $reward=round((float)$payment['amount_usd']*referralRate($pdo,$referrerId),8);
                $pdo->prepare('INSERT INTO referral_rewards (referrer_id,referred_id,amount_usd,status) VALUES (?,?,?,\'paid\')')->execute([$referrerId,$payment['user_id'],$reward]);
                $pdo->prepare('UPDATE users SET balance=balance+? WHERE id=?')->execute([$reward,$referrerId]);
                $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$referrerId,'Referral reward',$reward,'completed','REF-'.$payment['invoice_code']]);
            }
            $pdo->commit();
            response(['ok'=>true,'status'=>'paid']);
        }
        if ($action === 'cancel') {
            $pdo->prepare('UPDATE payments SET status="cancelled",txid=?,cancelled_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(string)($body['txid']??''),$paymentId]);
            $pdo->prepare('UPDATE transactions SET status="cancelled" WHERE user_id=? AND reference=?')->execute([$payment['user_id'],$payment['invoice_code']]);
            $pdo->prepare('INSERT INTO payment_events (payment_id,event_type,ip_address,user_agent) VALUES (?,?,?,?)')->execute([$paymentId,'cancelled',clientIp(),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
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
            $product=$pdo->prepare('SELECT vip_only FROM products WHERE id=?'); $product->execute([$payment['product_id']]); $vipOnly=(int)$product->fetchColumn();
            if ($vipOnly) {
                $user=$pdo->prepare('SELECT vip_until FROM users WHERE id=?'); $user->execute([$payment['user_id']]); $until=$user->fetchColumn();
                $base=max(time(),$until ? strtotime((string)$until) : time());
                $pdo->prepare('UPDATE users SET vip_until=? WHERE id=?')->execute([date('Y-m-d H:i:s',$base+30*86400),$payment['user_id']]);
            } else {
                $pdo->prepare('INSERT INTO purchases (user_id,product_id,payment_id,status) VALUES (?,?,?,\'active\')')->execute([$payment['user_id'],$payment['product_id'],$payment['id']]);
            }
            $pdo->prepare('UPDATE transactions SET status="completed" WHERE user_id=? AND reference=?')->execute([$payment['user_id'],$payment['invoice_code']]);
            $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$payment['user_id'],'Payment confirmed','Your mining product is now active.']);
            $ref=$pdo->prepare('SELECT referred_by FROM users WHERE id=?'); $ref->execute([$payment['user_id']]); $referrerId=(int)$ref->fetchColumn();
            if ($referrerId) {
                $reward=round((float)$payment['amount_usd']*referralRate($pdo,$referrerId),8);
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
