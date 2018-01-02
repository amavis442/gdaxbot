<?php

require 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Application;
use App\Bot\Gdaxbot;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use App\Util\Cache;

use Illuminate\Database\Capsule\Manager as Capsule;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

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
    'driver'    => 'mysql',
    'host'      => getenv('DB_HOST'),
    'database'  => getenv('DB_DATABASE'),
    'username'  => getenv('DB_USERNAME'),
    'password'  => getenv('DB_PASSWORD'),
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

//RedisAdapter::createConnection('redis://127.0.0.1:6379');
$client = new \Predis\Client();
$cache = new RedisAdapter($client, 300);
Cache::setCache($cache);

//$container = new ContainerBuilder();
//$container->register('mailer', 'Mailer');

/*

$cache = new RedisAdapter($client, '', 0);
*/
