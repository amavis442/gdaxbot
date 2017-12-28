<?php

require 'bootstrap.php';

use App\Bot\Gdaxbot;

echo date('Y-m-d H:i:s')." Start run\n";
$app = new Gdaxbot($conn);
$app->validateDatabase();
$app->cancelOrPurgeOrders();

echo date('Y-mecho-d H:i:s')." End run\n";
