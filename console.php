<?php

require 'bootstrap.php';

use App\Bot\Gdaxbot;

$settingsService = new \App\Services\SettingsService($conn);
$orderService = new \App\Services\OrderService($conn);


$client = new \GDAX\Clients\AuthenticatedClient(
        getenv('GDAX_API_KEY'), getenv('GDAX_API_SECRET'), getenv('GDAX_PASSWORD')
        );

$gdaxService = new \App\Services\GDaxService($client, getenv('CRYPTOCOIN'));


$app = new Gdaxbot($settingsService->getSettings(), $orderService, $gdaxService);


echo $gdaxService->getProductId()."\n";
echo "Ready to run\n";



$app->run();

