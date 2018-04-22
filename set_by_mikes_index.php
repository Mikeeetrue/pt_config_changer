<?php
die('script is not working for now');
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

$lowestSellValueChangeData = $symbolsData[intval(getenv('SELL_VALUE_COIN_INDEX')) - 1];
$lowestBuyValueChangeData = $symbolsData[intval(getenv('BUY_VALUE_COIN_INDEX')) - 1];
$lowestBuyTriggerChangeData = $symbolsData[intval(getenv('BUY_TRIGGER_COIN_INDEX')) - 1];

$sellValue = round(min(getenv('MAX_SELL_VALUE'),
    max(getenv('MIN_SELL_VALUE'), $lowestSellValueChangeData['priceChangePercent'])), 2);
$buyValue = round(min(getenv('MAX_BUY_VALUE'),
    max(getenv('MIN_BUY_VALUE'), $lowestBuyValueChangeData['priceChangePercent'])), 2);
$buyTrigger = round(min(getenv('MAX_BUY_TRIGGER'),
    max(getenv('MIN_BUY_TRIGGER'), $lowestBuyTriggerChangeData['priceChangePercent'])), 2);
$trailingBuy = 0.2;
if ($sellValue > 1 && $sellValue <= 3) {
    $trailingBuy = 0.3;
} elseif ($sellValue > 3 && $sellValue <= 6) {
    $trailingBuy = 0.5;
} elseif ($sellValue > 6 && $sellValue <= 10) {
    $trailingBuy = 0.7;
} elseif($sellValue > 10) {
    $trailingBuy = 1;
}
$log->info('Sell value', [$sellValue]);
$log->info('Buy value', [$buyValue]);
$log->info('Buy trigger', [$buyTrigger]);
$log->info('Trailing buy',[$trailingBuy]);

$ptGuzzle = new GuzzleHttp\Client([
    'base_uri' => getenv('PT_BASE_URL'),
    'timeout' => 30,
    'cookies' => true,
    'verify' => false
]);

$result = $ptGuzzle->request('POST', '/settingsapi/settings/load', [
    'query' => [
        'license' => getenv('PT_LICENSE_KEY'),
        'fileName' => 'PAIRS'
    ]

]);

if ($result->getStatusCode() != 200) {
    $log->alert('Cant get PAIRS file from PT');
    exit(1);
}
$pairsDataArray = json_decode($result->getBody()->getContents());
if(json_last_error() != JSON_ERROR_NONE)
{
    $log->alert('Cant decode PAIRS file from PT');
    exit(1);
}
$pairsConfigData = implode(PHP_EOL,$pairsDataArray);

$result = $ptGuzzle->request('POST', '/settingsapi/settings/load', [
    'query' => [
        'license' => getenv('PT_LICENSE_KEY'),
        'fileName' => 'DCA'
    ]

]);

if ($result->getStatusCode() != 200) {
    $log->alert('Cant get DCA file from PT');
    exit(1);
}
$dcaDataArray = json_decode($result->getBody()->getContents());
if(json_last_error() != JSON_ERROR_NONE)
{
    $log->alert('Cant decode DCA file from PT');
    exit(1);
}
$dcaConfigData = implode(PHP_EOL,$dcaDataArray);

$dcaConfigData = preg_replace('#^sell_value\s+=\s+[-0-9.]+#m', 'sell_value = ' . $sellValue, $dcaConfigData);
$dcaConfigData = preg_replace('#^trailing_profit\s+=\s+[-0-9.]+#m', 'trailing_profit = ' . $trailingBuy, $dcaConfigData);
$dcaConfigData = preg_replace('#^buy_value\s+=\s+[-0-9.]+#m', 'buy_value = ' . $buyValue, $dcaConfigData);
$dcaConfigData = preg_replace('#^buy_trigger\s+=\s+[-0-9.]+#m', 'buy_trigger = ' . $buyTrigger, $dcaConfigData);
$result = $ptGuzzle->request('POST', '/settingsapi/settings/save', [
    'query' => [
        'license' => getenv('PT_LICENSE_KEY'),
        'fileName' => 'DCA'
    ],
    'form_params' =>
        [
            'saveData' => $dcaConfigData
        ]

]);
if ($result->getStatusCode() != 200) {
    $log->alert('Cant update DCA file in PT');
    exit(1);
}

$pairsConfigData = preg_replace('#^ALL_sell_value\s+=\s+[-0-9.]+#m', 'ALL_sell_value = ' . $sellValue,
    $pairsConfigData);
$pairsConfigData = preg_replace('#^ALL_buy_value\s+=\s+[-0-9.]+#m', 'ALL_buy_value = ' . $buyValue,
    $pairsConfigData);
$pairsConfigData = preg_replace('#^ALL_trailing_profit\s+=\s+[-0-9.]+#m', 'ALL_trailing_profit = ' . $trailingBuy,
    $pairsConfigData);
$result = $ptGuzzle->request('POST', '/settingsapi/settings/save', [
    'query' => [
        'license' => getenv('PT_LICENSE_KEY'),
        'fileName' => 'PAIRS'
    ],
    'form_params' =>
        [
            'saveData' => $pairsConfigData
        ]

]);
if ($result->getStatusCode() != 200) {
    $log->alert('Cant update PAIRS file in PT');
    exit(1);
}
