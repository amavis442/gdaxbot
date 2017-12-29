<?php

require 'bootstrap.php';

use App\Bot\Gdaxbot;

echo date('Y-m-d H:i:s')." Start run\n";
$app = new Gdaxbot($conn);

/*$app->actualize();
$app->actualizeBuys();
$app->actualizeSells();
$app->cancelOldBuyOrders();*/

$result = $app->buy(1);
//var_dump($result);
/*
$app->cancelOrPurgeBuyOrders();

$app->buy(1);
$app->sell();
*/
