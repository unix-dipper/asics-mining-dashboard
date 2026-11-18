<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $mysqlHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
    $mysqlPort = getenv('MYSQL_PORT') ?: '3306';
    $mysqlDb = getenv('MYSQL_DATABASE') ?: 'asics_mining';
    $mysqlUser = getenv('MYSQL_USER') ?: 'root';
    $mysqlPass = getenv('MYSQL_PASSWORD') ?: '';

    try {
        $pdo = new PDO(
            "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlDb};charset=utf8mb4",
            $mysqlUser,
            $mysqlPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        initializeDatabase($pdo, 'mysql');
    } catch (PDOException $mysqlError) {
        $pdo = new PDO(
            'sqlite:' . __DIR__ . '/database.sqlite',
            null,
            null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        initializeDatabase($pdo, 'sqlite');
    }
    return $pdo;
}

function initializeDatabase(PDO $pdo, string $driver): void {
    if ($driver === 'mysql') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL, email VARCHAR(191) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, account_id VARCHAR(191) NOT NULL UNIQUE, uid VARCHAR(191) NOT NULL UNIQUE, balance DECIMAL(16,8) NOT NULL DEFAULT 0, is_admin TINYINT(1) NOT NULL DEFAULT 0, avatar VARCHAR(255) NULL, bio VARCHAR(500) NULL, referral_code VARCHAR(32) NULL UNIQUE, referred_by INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS products (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL, price_usd DECIMAL(16,8) NOT NULL, total_return_usd DECIMAL(16,8) NOT NULL, daily_profit_usd DECIMAL(16,8) NOT NULL, hashrate DECIMAL(16,8) NOT NULL, icon VARCHAR(32) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS payments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL, invoice_code VARCHAR(191) NOT NULL UNIQUE, network VARCHAR(32) NOT NULL, address VARCHAR(191) NOT NULL, amount_usd DECIMAL(16,8) NOT NULL, amount_crypto DECIMAL(16,8) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT "pending", txid VARCHAR(191) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at TIMESTAMP NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id)) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS purchases (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL, payment_id INT UNSIGNED NULL, status VARCHAR(32) NOT NULL DEFAULT "active", purchased_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, earnings_paid DECIMAL(16,8) NOT NULL DEFAULT 0, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id), FOREIGN KEY (payment_id) REFERENCES payments(id)) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS transactions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, type VARCHAR(191) NOT NULL, amount DECIMAL(16,8) NOT NULL, status VARCHAR(32) NOT NULL, reference VARCHAR(191) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, title VARCHAR(191) NOT NULL, message TEXT NOT NULL, read_at TIMESTAMP NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS wallet_addresses (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, coin VARCHAR(16) NOT NULL, address VARCHAR(255) NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY wallet_user_coin (user_id, coin), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS withdrawals (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, coin VARCHAR(16) NOT NULL, address VARCHAR(255) NOT NULL, amount_usd DECIMAL(16,8) NOT NULL, amount_coin DECIMAL(16,8) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT "pending", txid VARCHAR(191) NULL, admin_note TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, processed_at TIMESTAMP NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS referral_rewards (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, referrer_id INT UNSIGNED NOT NULL, referred_id INT UNSIGNED NOT NULL, amount_usd DECIMAL(16,8) NOT NULL DEFAULT 0, status VARCHAR(32) NOT NULL DEFAULT "pending", created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
    } else {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, account_id TEXT NOT NULL UNIQUE, uid TEXT NOT NULL UNIQUE, balance REAL NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, price_usd REAL NOT NULL, total_return_usd REAL NOT NULL, daily_profit_usd REAL NOT NULL, hashrate REAL NOT NULL, icon TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1);
            CREATE TABLE IF NOT EXISTS payments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, invoice_code TEXT NOT NULL UNIQUE, network TEXT NOT NULL, address TEXT NOT NULL, amount_usd REAL NOT NULL, amount_crypto REAL NOT NULL, status TEXT NOT NULL DEFAULT "pending", txid TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at TEXT, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id));
            CREATE TABLE IF NOT EXISTS purchases (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, payment_id INTEGER, status TEXT NOT NULL DEFAULT "active", purchased_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, earnings_paid REAL NOT NULL DEFAULT 0, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id), FOREIGN KEY (payment_id) REFERENCES payments(id));
            CREATE TABLE IF NOT EXISTS transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL, amount REAL NOT NULL, status TEXT NOT NULL, reference TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, read_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS wallet_addresses (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, coin TEXT NOT NULL, address TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, coin), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS withdrawals (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, coin TEXT NOT NULL, address TEXT NOT NULL, amount_usd REAL NOT NULL, amount_coin REAL NOT NULL, status TEXT NOT NULL DEFAULT "pending", txid TEXT, admin_note TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, processed_at TEXT, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS referral_rewards (id INTEGER PRIMARY KEY AUTOINCREMENT, referrer_id INTEGER NOT NULL, referred_id INTEGER NOT NULL, amount_usd REAL NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT "pending", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE);
        ');
    }

    if ($driver === 'mysql') {
        ensureMySqlColumn($pdo, 'users', 'is_admin', 'TINYINT(1) NOT NULL DEFAULT 0');
        ensureMySqlColumn($pdo, 'users', 'avatar', 'VARCHAR(255) NULL');
        ensureMySqlColumn($pdo, 'users', 'bio', 'VARCHAR(500) NULL');
        ensureMySqlColumn($pdo, 'users', 'referral_code', 'VARCHAR(32) NULL');
        ensureMySqlColumn($pdo, 'users', 'referred_by', 'INT UNSIGNED NULL');
    } else {
        ensureSqliteColumn($pdo, 'users', 'is_admin', 'INTEGER NOT NULL DEFAULT 0');
        ensureSqliteColumn($pdo, 'users', 'avatar', 'TEXT');
        ensureSqliteColumn($pdo, 'users', 'bio', 'TEXT');
        ensureSqliteColumn($pdo, 'users', 'referral_code', 'TEXT');
        ensureSqliteColumn($pdo, 'users', 'referred_by', 'INTEGER');
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO products (name, price_usd, total_return_usd, daily_profit_usd, hashrate, icon) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ([['Antminer DR7 127 Th',10,19,2.71,127,'▧'],['Antminer KA3 (166 Th)',30,61,3.39,166,'▦'],['Bitmain Antminer S23',50,111,3.70,195,'▥'],['Bitmain Antminer S21 Pro',100,350,11.67,234,'▤']] as $product) $stmt->execute($product);
    }

    $demo = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $demo->execute(['demo@asics.test']);
    if (!$demo->fetchColumn()) {
        $pdo->prepare('INSERT INTO users (name,email,password_hash,account_id,uid,balance) VALUES (?,?,?,?,?,?)')->execute(['Demo User','demo@asics.test',password_hash('demo123', PASSWORD_DEFAULT),'0505050','8358-4562-4521',400]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference) VALUES (?,?,?,?,?)')->execute([$userId,'Welcome bonus',400,'completed','WELCOME']);
        $pdo->prepare('INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)')->execute([$userId,'Welcome to ASICs Mining','Your account is ready to start mining.']);
    }

    $pdo->prepare('SELECT id FROM users WHERE email = ?')->execute(['demo@asics.test']);
    $stmt = $pdo->prepare('SELECT id, referral_code FROM users WHERE email = ?');
    $stmt->execute(['demo@asics.test']);
    $demoUser = $stmt->fetch();
    if ($demoUser) {
        $pdo->prepare('UPDATE users SET is_admin = 1, referral_code = COALESCE(referral_code, ?) WHERE id = ?')
            ->execute(['ASIC-DEMO', (int) $demoUser['id']]);
    }

    $pdo->exec('UPDATE users SET referral_code = LOWER(SUBSTR(account_id, 1, 8)) WHERE referral_code IS NULL');
}

function ensureSqliteColumn(PDO $pdo, string $table, string $column, string $definition): void {
    $columns = $pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll();
    foreach ($columns as $existing) {
        if ($existing['name'] === $column) return;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
}

function ensureMySqlColumn(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}
