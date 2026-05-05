<?php

date_default_timezone_set('Europe/Istanbul');

error_reporting(E_ALL);
ini_set('display_errors', 0);

require __DIR__ . '/../../vendor/autoload.php';

use Slim\App;
use App\enum\LogTypes;
use App\util\CLI;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$settings['settings'] = [
    'displayErrorDetails' => $_ENV['displayErrorDetails'],
    'debug' => $_ENV['debug']
];

$app = new App($settings);

$container = $app->getContainer();

$container['db'] = function ($container) {
    $dsn = "mysql:host=" . $_ENV['MYSQL_HOST'] . ";dbname=" . $_ENV['MYSQL_DATABASE'] . ";charset=utf8mb4";
    try {
        $db = new \PDO($dsn, $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASSWORD']);
    } catch (\Exception $e) {
        CLI::printLog('Database access problem: ' . $e->getMessage(), LogTypes::ERROR);
        die;
    }

    return $db;
};