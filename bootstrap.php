<?php

require 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Application;
use App\Bot\Gdaxbot;

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
