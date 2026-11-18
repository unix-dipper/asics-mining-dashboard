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
    $fallback = ['BTC'=>65000,'ETH'=>3400,'TRX'=>0.14,'USDT-TRC20'=>1,'USDT-ERC20'=>1,'SOL'=>210,'LTC'=>95,'DOGE'=>0.22];
    $live = liveCoinRates();
    $rates = $fallback;
    foreach ($live as $code => $rate) {
        if (is_numeric($rate) && (float)$rate > 0) $rates[$code]=(float)$rate;
    }
    return [
        'BTC'=>['name'=>'Bitcoin','symbol'=>'BTC','rate'=>$rates['BTC'],'address'=>config('BTC_ADDRESS') ?: 'bc1q-demo-btc-address'],
        'ETH'=>['name'=>'Ethereum','symbol'=>'ETH','rate'=>$rates['ETH'],'address'=>config('ETH_ADDRESS') ?: '0xDemoEthereumAddress'],
        'TRX'=>['name'=>'TRON','symbol'=>'TRX','rate'=>$rates['TRX'],'address'=>config('TRX_ADDRESS') ?: 'TDemoTronAddress'],
        'USDT-TRC20'=>['name'=>'Tether TRC20','symbol'=>'USDT','rate'=>1,'address'=>config('USDT_TRC20_ADDRESS') ?: 'TDemoUsdtTrc20Address'],
        'USDT-ERC20'=>['name'=>'Tether ERC20','symbol'=>'USDT','rate'=>1,'address'=>config('USDT_ERC20_ADDRESS') ?: '0xDemoUsdtErc20Address'],
        'SOL'=>['name'=>'Solana','symbol'=>'SOL','rate'=>$rates['SOL'],'address'=>config('SOL_ADDRESS') ?: 'DemoSolanaAddress'],
        'LTC'=>['name'=>'Litecoin','symbol'=>'LTC','rate'=>$rates['LTC'],'address'=>config('LTC_ADDRESS') ?: 'ldemoLitecoinAddress'],
        'DOGE'=>['name'=>'Dogecoin','symbol'=>'DOGE','rate'=>$rates['DOGE'],'address'=>config('DOGE_ADDRESS') ?: 'DdemoDogecoinAddress'],
    ];
}

function liveCoinRates(): array {
    $cache = __DIR__ . '/cache/coin-rates.json';
    if (is_file($cache) && time() - (int)filemtime($cache) < 300) {
        $cached = json_decode((string)file_get_contents($cache), true);
        if (is_array($cached) && isset($cached['BTC'])) return $cached;
    }
    $url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,tron,tether,solana,litecoin,dogecoin&vs_currencies=usd';
    $json = '';
    if (function_exists('curl_init')) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_USERAGENT=>'ASICs-Mining/1.0']);
        $json=(string)curl_exec($ch); curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $context=stream_context_create(['http'=>['timeout'=>8,'header'=>"User-Agent: ASICs-Mining/1.0\r\n"]]);
        $json=(string)@file_get_contents($url,false,$context);
    }
    $data=json_decode($json,true);
    if (!is_array($data)||empty($data['bitcoin']['usd'])) return [];
    $rates=['BTC'=>(float)$data['bitcoin']['usd'],'ETH'=>(float)$data['ethereum']['usd'],'TRX'=>(float)$data['tron']['usd'],'USDT-TRC20'=>(float)$data['tether']['usd'],'USDT-ERC20'=>(float)$data['tether']['usd'],'SOL'=>(float)$data['solana']['usd'],'LTC'=>(float)$data['litecoin']['usd'],'DOGE'=>(float)$data['dogecoin']['usd']];
    if (!is_dir(dirname($cache))) mkdir(dirname($cache),0755,true);
    file_put_contents($cache,json_encode($rates));
    return $rates;
}
