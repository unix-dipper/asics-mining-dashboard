<?php
declare(strict_types=1);

const APP_NAME = 'ASICs Mining';
const PAYMENT_NETWORK = 'BTC';
const BTC_USD_RATE = 65000.00;
const DEFAULT_PAYMENT_ADDRESS = 'tb1q-demo-payment-address-configure-me';

function config(string $key): string {
    $value = getenv($key);
    return $value === false ? '' : $value;
}

function coinConfig(): array {
    return [
        'BTC' => ['name'=>'Bitcoin','symbol'=>'BTC','rate'=>65000,'address'=>config('BTC_ADDRESS') ?: 'bc1q-demo-btc-address'],
        'ETH' => ['name'=>'Ethereum','symbol'=>'ETH','rate'=>3400,'address'=>config('ETH_ADDRESS') ?: '0xDemoEthereumAddress'],
        'TRX' => ['name'=>'TRON','symbol'=>'TRX','rate'=>0.14,'address'=>config('TRX_ADDRESS') ?: 'TDemoTronAddress'],
        'USDT-TRC20' => ['name'=>'Tether TRC20','symbol'=>'USDT','rate'=>1,'address'=>config('USDT_TRC20_ADDRESS') ?: 'TDemoUsdtTrc20Address'],
        'USDT-ERC20' => ['name'=>'Tether ERC20','symbol'=>'USDT','rate'=>1,'address'=>config('USDT_ERC20_ADDRESS') ?: '0xDemoUsdtErc20Address'],
        'SOL' => ['name'=>'Solana','symbol'=>'SOL','rate'=>210,'address'=>config('SOL_ADDRESS') ?: 'DemoSolanaAddress'],
        'LTC' => ['name'=>'Litecoin','symbol'=>'LTC','rate'=>95,'address'=>config('LTC_ADDRESS') ?: 'ldemoLitecoinAddress'],
        'DOGE' => ['name'=>'Dogecoin','symbol'=>'DOGE','rate'=>0.22,'address'=>config('DOGE_ADDRESS') ?: 'DdemoDogecoinAddress'],
    ];
}
