<?php
/**
 * This script calculates Mike's index and sets it as sell value
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

$api = new \Binance\API(getenv('BINANCE_API_KEY'), getenv('BINANCE_API_SECRET'));
$symbols = $api->prevDay();
$symbolsData = [];
//filter the market
foreach ($symbols as $symbolEntry) {
    if (endsWith($symbolEntry['symbol'], getenv('MARKET'))) {
        $symbolsData[] = $symbolEntry;
    }
}
usort($symbolsData, function ($a, $b) {
    return $b['priceChangePercent'] <=> $a['priceChangePercent'];
});

$lowestChangeData = $symbolsData[intval(getenv('SELL_VALUE_COIN_INDEX')) - 1];

$sellValue = round(min(getenv('MAX_SELL_VALUE'),
    max(getenv('MIN_SELL_VALUE'), $lowestChangeData['priceChangePercent'])), 2);
$trailingBuy = 0.1;
if ($sellValue > 1 && $sellValue < 3) {
    $trailingBuy = 0.3;
} elseif ($sellValue > 3 && $sellValue < 6) {
    $trailingBuy = 0.5;
} elseif ($sellValue > 6 && $sellValue < 10) {
    $trailingBuy = 0.7;
} else {
    $trailingBuy = 1;
}
$log->info('Sell value', [$sellValue]);
$log->info('Trailing buy',[$trailingBuy]);
$pairsConfigFilePath = getenv('PT_ROOT_DIR') . 'trading' . DIRECTORY_SEPARATOR . 'PAIRS.properties';
$dcaConfigFilePath = getenv('PT_ROOT_DIR') . 'trading' . DIRECTORY_SEPARATOR . 'DCA.properties';
//check if PT config file exists
if (!file_exists($pairsConfigFilePath)) {
    $log->alert('PT pairs config doesnt exist. WFT???');
    exit(1);
}

if (!is_readable($pairsConfigFilePath)) {
    $log->alert('PT pairs config it not readable.');
    exit(1);
}

if (!is_writable($pairsConfigFilePath)) {
    $log->alert('PT pairs config is not writable.');
    exit(1);
}
//check if PT config file exists
if (!file_exists($dcaConfigFilePath)) {
    $log->alert('PT dca config doesnt exist. WFT???');
    exit(1);
}

if (!is_readable($dcaConfigFilePath)) {
    $log->alert('PT dca config it not readable.');
    exit(1);
}

if (!is_writable($dcaConfigFilePath)) {
    $log->alert('PT dca config is not writable.');
    exit(1);
}

$pairsConfigData = file_get_contents($pairsConfigFilePath);
$dcaConfigData = file_get_contents($dcaConfigFilePath);

$dcaConfigData = preg_replace('#^sell_value\s+=\s+[-0-9.]+#m', 'sell_value = ' . $sellValue, $dcaConfigData);
$dcaConfigData = preg_replace('#^trailing_profit\s+=\s+[-0-9.]+#m', 'trailing_profit = ' . $trailingBuy, $dcaConfigData);
file_put_contents($dcaConfigFilePath, $dcaConfigData);

$pairsConfigData = preg_replace('#^ALL_sell_value\s+=\s+[-0-9.]+#m', 'ALL_sell_value = ' . $sellValue,
    $pairsConfigData);
$pairsConfigData = preg_replace('#^ALL_trailing_profit\s+=\s+[-0-9.]+#m', 'ALL_trailing_profit = ' . $trailingBuy,
    $pairsConfigData);
file_put_contents($pairsConfigFilePath, $pairsConfigData);