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
