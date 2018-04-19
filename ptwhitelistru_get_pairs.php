<?php

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\TransferStats;
use Jokaorgua\Monolog\Handler\TelegramHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('GMT');
//loading .env data
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// create a log channel
$log = new Logger('ptwhitelistru_get_pairs');
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$log->pushHandler(new TelegramHandler(getenv('TELEGRAM_BOT_TOKEN'), getenv('TELEGRAM_CHAT_ID'), Logger::ALERT));

$container = [];
$history = Middleware::history($container);

$stack = HandlerStack::create();
// Add the history middleware to the handler stack.
$stack->push($history);
$params = [
    'base_uri' => 'http://ptwhitelist.ru/',
    'timeout' => 5,
    'cookies' => true,
    'handler' => $stack
];


$guzzle = new GuzzleHttp\Client($params);

//login to ptwhitelist.ru
/**
 * @var \GuzzleHttp\Psr7\Uri $url
 */
$url = '';
$guzzle->request('POST', '/index.php?route=account/login/login',
    [
        'form_params' => [
            'username' => getenv('PTWHITELISTRU_LOGIN'),
            'pass' => getenv('PTWHITELISTRU_PASSWORD'),
        ],
        'on_stats' => function (TransferStats $stats) use (&$url) {
            $url = $stats->getEffectiveUri();
        }
    ]);

if ($url->getQuery() != 'route=common/home') {
    $log->alert('Cant login to ptwhitelist.ru');
    exit(1);
}

//send filter query
$result = $guzzle->request('POST', '/index.php?route=common/home',
    [
        'form_params' => [
            'para' => getenv('MARKET'),
            'outdays' => getenv('PTWHITELISTRU_DAYS_TO_ANALYZE'),
            'filtervol' => getenv('PTWHITELISTRU_VOLUME'),
            'filtervoldays' => getenv('PTWHITELISTRU_VOLUME_DAYS'),
            'filtervolcount' => getenv('PTWHITELISTRU_VOLUME_COUNT'),
            'filternewcoin' => getenv('PTWHITELISTRU_NEW_COIN_MIN_DAYS'),
            'filterpricedays' => getenv('PTWHITELISTRU_PRICE_DAYS'),
            'filterpricepercent' => getenv('PTWHITELISTRU_PRICE_PERCENT'),
            'filterpricepercentdown' => getenv('PTWHITELISTRU_PRICE_PERCENT_DOWN'),
            'filtervola' => getenv('PTWHITELISTRU_MIN_VOLATILITY_PERCENT'),
            'filtervoladays' => getenv('PTWHITELISTRU_MIN_VOLATILITY_DAYS'),
            'filtervolacount' => getenv('PTWHITELISTRU_MIN_VOLATILITY_COUNT'),
        ]
    ]);
$responseCode = $result->getBody()->getContents();
$crawler = new \Symfony\Component\DomCrawler\Crawler($responseCode);
$PAIRS = [];
foreach ($crawler->filter('div.symboldiv.aligndiv > div[data-symb]') as $entry) {
    $PAIRS[] = $entry->getAttribute('data-symb');
}
if (empty($PAIRS)) {
    $log->alert('Found ZERO pairs for enabling. Will disable everything.');
    exit(1);
}
//check if PT config file exists
if (!file_exists(getenv('PT_ROOT_DIR') . 'trading' . DIRECTORY_SEPARATOR . 'PAIRS.properties')) {
    $log->alert('PT pairs config doesnt exist. WFT???');
    exit(1);
}

if (!is_readable(getenv('PT_ROOT_DIR') . 'trading' . DIRECTORY_SEPARATOR . 'PAIRS.properties')) {
    $log->alert('PT pairs config it not readable.');
    exit(1);
}

if (!is_writable(getenv('PT_ROOT_DIR') . 'trading' . DIRECTORY_SEPARATOR . 'PAIRS.properties')) {
    $log->alert('PT pairs config is not writable.');
    exit(1);
}

$pairsConfigData = trim(file_get_contents(getenv('PT_ROOT_DIR') . 'trading' . DIRECTORY_SEPARATOR . 'PAIRS.properties'));

// ensure that ALL_sell_only_mode enabled
if (!preg_match('#ALL_sell_only_mode\s+=\s+true#', $pairsConfigData)) {
    $log->alert('Please enable ALL_sell_only_mode');
    exit(1);
}

//remove all false sell modes
$pairsConfigData = preg_replace("/#PTWHITELISTRU_PAIRS_UPDATER_START.*PTWHITELISTRU_PAIRS_UPDATER_END/s", '', $pairsConfigData);
$pairsConfigData = preg_replace("/\n+/s", PHP_EOL, $pairsConfigData);
$pairsConfigData = trim($pairsConfigData) . PHP_EOL . PHP_EOL."#PTWHITELISTRU_PAIRS_UPDATER_START";


foreach ($PAIRS as $pair) {
    $log->info($pair . strtoupper(getenv('MARKET')) . '_sell_only_mode = false');
    $pairsConfigData .= PHP_EOL . $pair . getenv('MARKET') . '_sell_only_mode = false';
}
$pairsConfigData .= PHP_EOL.'#PTWHITELISTRU_PAIRS_UPDATER_END'.PHP_EOL;
file_put_contents(getenv('PT_ROOT_DIR') . 'trading' . DIRECTORY_SEPARATOR . 'PAIRS.properties', $pairsConfigData);


