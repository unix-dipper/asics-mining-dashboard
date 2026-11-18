PRAGMA foreign_keys = ON;

INSERT OR IGNORE INTO users (
    id,
    name,
    email,
    password_hash,
    account_id,
    uid,
    balance
) VALUES (
    1,
    'Demo User',
    'demo@asics.test',
    '$2y$12$1S4.glEeydnZZ26Xe.9Ep.UkFQfWYKKBQMukA8VLGTUoKjyIwkOBq',
    '0505050',
    '8358-4562-4521',
    400
);

INSERT OR IGNORE INTO products (id, name, price_usd, total_return_usd, daily_profit_usd, hashrate, icon, active)
VALUES (1, 'Antminer DR7 127 Th', 10.00, 19.00, 2.71, 127, '▧', 1);

INSERT OR IGNORE INTO products (id, name, price_usd, total_return_usd, daily_profit_usd, hashrate, icon, active)
VALUES (2, 'Antminer KA3 (166 Th)', 30.00, 61.00, 3.39, 166, '▦', 1);

INSERT OR IGNORE INTO products (id, name, price_usd, total_return_usd, daily_profit_usd, hashrate, icon, active)
VALUES (3, 'Bitmain Antminer S23', 50.00, 111.00, 3.70, 195, '▥', 1);

INSERT OR IGNORE INTO products (id, name, price_usd, total_return_usd, daily_profit_usd, hashrate, icon, active)
VALUES (4, 'Bitmain Antminer S21 Pro', 100.00, 350.00, 11.67, 234, '▤', 1);

INSERT OR IGNORE INTO transactions (id, user_id, type, amount, status, reference)
VALUES (1, 1, 'Welcome bonus', 400, 'completed', 'WELCOME');

INSERT OR IGNORE INTO notifications (id, user_id, title, message)
VALUES (1, 1, 'Welcome to ASICs Mining', 'Your account is ready to start mining.');
