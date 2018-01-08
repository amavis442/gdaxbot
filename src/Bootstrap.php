<?php
require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Application;
use App\Bot\Gdaxbot;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use App\Util\Cache;
use Illuminate\Database\Capsule\Manager as Capsule;

if (!isset($_SERVER['APP_ENV'])) {
    (new Dotenv())->load(__DIR__ . '/../.env');
}

$config = new \Doctrine\DBAL\Configuration();

$connectionParams = array(
    'user' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
    'dbname' => getenv('DB_DATABASE'),
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT'),
    'driver' => 'pdo_mysql',
);

$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => getenv('DB_HOST'),
    'database' => getenv('DB_DATABASE'),
    'username' => getenv('DB_USERNAME'),
    'password' => getenv('DB_PASSWORD'),
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$client = new \Predis\Client();
$cache = new RedisAdapter($client, 300);
Cache::setCache($cache);

$container = new ContainerBuilder();



$bot = Yaml::parseFile(__DIR__ . '/../config/bot.yml');
$availableStrategies = $bot['strategies']['available'];
$activeStrategy = $bot['strategies']['active'];

$availableRules = $bot['rules']['available'];
$activeBuyRule = $bot['rules']['buy']['active'];
$activeSellRule = $bot['rules']['sell']['active'];
$sandbox = $bot['settings']['gdax']['sandbox'];

$container->register('bot.strategy', $availableStrategies[$activeStrategy]);
$container->register('bot.buy.rule', $availableRules[$activeBuyRule]);
$container->register('bot.sell.rule', $availableRules[$activeSellRule]);

$container->register('bot.settings', '\App\Services\SettingsService');
$container->register('bot.service.order', '\App\Services\OrderService');
$container->register('bot.service.position', '\App\Services\PositionService');
$container->register('bot.service.gdax', '\App\Services\GDaxService')
    ->addMethodCall('setCoin', [getenv('CRYPTOCOIN')])
    ->addMethodCall('connect', [$sandbox]);
$container->register('bot.httpclient', '\GuzzleHttp\Client');
$container->register('bot.rule.stoploss', '\App\Rules\Stoploss');
