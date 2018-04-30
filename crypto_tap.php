<?php

/**
 * This script calculates trailing and sell value for CryptoTap strategy
 *
 */

use Jokaorgua\Monolog\Handler\TelegramHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// create a log channel
$log = new Logger('name');
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
if (!empty(getenv('TELEGRAM_BOT_TOKEN'))) {
    $log->pushHandler(new TelegramHandler(getenv('TELEGRAM_BOT_TOKEN'), getenv('TELEGRAM_CHAT_ID'), Logger::ALERT));
}


$ptGuzzle = new GuzzleHttp\Client([
    'base_uri' => getenv('PT_BASE_URL'),
    'timeout' => 30,
    'cookies' => true,
    'verify' => false
]);

$pt = new \Classes\ProfitTrailer($ptGuzzle, getenv('PT_LICENSE_KEY'), getenv('PT_API_TOKEN'));
try {
    $pairsConfigData = $pt->getConfigData('PAIRS');
    $dcaConfigData = $pt->getConfigData('DCA');

    $result = $ptGuzzle->request('GET', '/api/data',
        [
            'query' =>
                [
                    'token' => getenv('PT_API_TOKEN')
                ]
        ]);
    if ($result->getStatusCode() != 200) {
        $log->alert('Cant get balance data from PT');
        exit(1);
    }

    $ptData = json_decode($result->getBody()->getContents());
    $ptCurrentBalance = $ptData->realBalance;
} catch (Exception $e) {
    $log->alert($e->getMessage());
    exit(1);
}

$log->info('Current balance', [$ptCurrentBalance]);

$ptStartBalance = 0;
preg_match('#start_balance\s+=\s+(?<start_balance>[0-9.]+)#', $pairsConfigData, $matches);
if (!empty($matches['start_balance'])) {
    $ptStartBalance = $matches['start_balance'];
}
if ($ptStartBalance == 0) {
    $log->alert('Set start balance');
    exit(1);
}
$balanceLeft = round($ptCurrentBalance / $ptStartBalance * 100, 2);

$log->info('Start balance', [$ptStartBalance]);
$log->info('Balance left percent', [$balanceLeft]);
$sellValue = 0;
$trailingProfit = 0.2;

if ($balanceLeft >= 0 && $balanceLeft <= 10) {
    $sellValue = 0.1;
} elseif ($balanceLeft > 10 && $balanceLeft <= 15) {
    $sellValue = 1;
} elseif ($balanceLeft > 15) {
    $sellValue = round($balanceLeft / 10, 2);
}
$log->info('Sell value', [$sellValue]);

if ($sellValue < 1) {
    $trailingProfit = 0.1;
} else {
    $trailingProfit = round($sellValue / 10, 2);
}
$log->info('Trailing', [$trailingProfit]);


$dcaConfigData = preg_replace('#(DEFAULT_DCA_B_sell_value\s+=)\s+[-0-9.]+#s', '$1 ' . $sellValue, $dcaConfigData);
$dcaConfigData = preg_replace('#(DEFAULT_DCA_trailing_buy\s+=)\s+[-0-9.]+#m', '$1 ' . $trailingProfit, $dcaConfigData);
echo $dcaConfigData;

$pairsConfigData = preg_replace('#(DEFAULT_A_sell_value\s+=)\s+[-0-9.]+#s', '$1 ' . $sellValue, $pairsConfigData);
$pairsConfigData = preg_replace('#(DEFAULT_trailing_profit\s+=)\s+[-0-9.]+#m', '$1 ' . $trailingProfit,
    $pairsConfigData);

echo $pairsConfigData;
dd('end');
try {
    $pt->saveConfigData('PAIRS', $pairsConfigData);
    $pt->saveConfigData('DCA', $dcaConfigData);
} catch (Exception $e) {
    $log->alert($e->getMessage());
    exit(1);
}
