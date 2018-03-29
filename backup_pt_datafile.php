<?php
/**
 * Script backs up the ProfitTrailerData.json to different location with json check
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
$BACKUP_PATH = getenv('BACKUP_PATH');
if (empty($BACKUP_PATH)) {
    $BACKUP_PATH = '/tmp/';
}
$ptDataFilePath = getenv('PT_ROOT_DIR') . 'ProfitTrailerData.json';
//check if PT config file exists
if (!file_exists($ptDataFilePath)) {
    $log->alert('PT data file doesnt exist. WFT???');
    exit(1);
}

if (!is_readable($ptDataFilePath)) {
    $log->alert('PT data file it not readable.');
    exit(1);
}

$data = file_get_contents($ptDataFilePath);
$data = json_decode($data, true);
if (json_last_error() != JSON_ERROR_NONE) {
    $log->alert('!!!PT data file is broken!!!');
    exit(1);
}

if (file_exists(getenv('BACKUP_PATH') . 'ProfitTrailerData.json') && !is_writable(getenv('BACKUP_PATH') . 'ProfitTrailerData.json')) {
    $log->alert('Destination path for backup is not writable', [getenv('BACKUP_PATH') . 'ProfitTrailerData.json']);
    exit(1);
}
copy($ptDataFilePath, getenv('BACKUP_PATH') . 'ProfitTrailerData.json');