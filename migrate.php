<?php

require 'bootstrap.php';

use App\Bot\Gdaxbot;

$app = new Gdaxbot($conn);
$app->createDatabase();

