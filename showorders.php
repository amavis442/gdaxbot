<?php

require 'bootstrap.php';

use App\Bot\Gdaxbot;

echo date('Y-m-d H:i:s')." Start run\n";
$app = new Gdaxbot($conn);
$app->listRowsFromDatabase();
echo date('Y-m-d H:i:s')." End run\n";
