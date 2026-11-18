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
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL, email VARCHAR(191) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, account_id VARCHAR(191) NOT NULL UNIQUE, uid VARCHAR(191) NOT NULL UNIQUE, balance DECIMAL(16,8) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS products (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL, price_usd DECIMAL(16,8) NOT NULL, total_return_usd DECIMAL(16,8) NOT NULL, daily_profit_usd DECIMAL(16,8) NOT NULL, hashrate DECIMAL(16,8) NOT NULL, icon VARCHAR(32) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS payments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL, invoice_code VARCHAR(191) NOT NULL UNIQUE, network VARCHAR(32) NOT NULL, address VARCHAR(191) NOT NULL, amount_usd DECIMAL(16,8) NOT NULL, amount_crypto DECIMAL(16,8) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT "pending", txid VARCHAR(191) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, paid_at TIMESTAMP NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id)) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS purchases (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL, payment_id INT UNSIGNED NULL, status VARCHAR(32) NOT NULL DEFAULT "active", purchased_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, earnings_paid DECIMAL(16,8) NOT NULL DEFAULT 0, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id), FOREIGN KEY (payment_id) REFERENCES payments(id)) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS transactions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, type VARCHAR(191) NOT NULL, amount DECIMAL(16,8) NOT NULL, status VARCHAR(32) NOT NULL, reference VARCHAR(191) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
        $pdo->exec('CREATE TABLE IF NOT EXISTS notifications (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, title VARCHAR(191) NOT NULL, message TEXT NOT NULL, read_at TIMESTAMP NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB');
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
        ');
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
}
