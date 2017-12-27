<?php

require 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Console\Application;

use App\Bot\Gdaxbot;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$app = new Gdaxbot();
$app->createDatabase();

