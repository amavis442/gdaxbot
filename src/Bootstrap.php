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

$bot = Yaml::parseFile(__DIR__ . '/../config/bot.yml');
$availableStrategies = $bot['strategies']['available'];
$activeStrategy = $bot['strategies']['active'];

$availableRules = $bot['rules']['available'];
$activeBuyRule = $bot['rules']['buy']['active'];
$activeSellRule = $bot['rules']['sell']['active'];
$sandbox = $bot['settings']['gdax']['sandbox'];


require __DIR__ .'/Container.php';

