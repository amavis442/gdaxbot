#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/bootstrap.php';

use Symfony\Component\Console\Application;

$application = new Application();

$settings = new \App\Commands\SettingsCommand();
$application->add($settings);
$settings->setConn($conn);

$bot = new \App\Commands\RunBotCommand();
$application->add($bot);
$bot->setConn($conn);

$application->add(new \App\Commands\ReportCommand());

$openordersReports = new \App\Commands\ReportOpenOrdersCommand();
$openordersReports->setConn($conn);
$application->add($openordersReports);

$changeSellOrder = new \App\Commands\ChangeSellOrderCommand();
$changeSellOrder->setConn($conn);
$application->add($changeSellOrder);

$reportProfits = new \App\Commands\ReportProfitsCommand();
$reportProfits->setConn($conn);
$application->add($reportProfits);

$websocketClient = new \App\Commands\GdaxWebsocketCommand();
$websocketClient->setCache($cache);
$websocketClient->setConn($conn);
$application->add($websocketClient);

/** 
 * For testing
 */
$testCandles = new \App\Commands\TestCandlesCommand();
$testCandles->setCache($cache);
$application->add($testCandles);

$testTrends = new \App\Commands\TestTrendsCommand();
$testTrends->setCache($cache);
$application->add($testTrends);

$testSignals = new \App\Commands\TestSignalsCommand();
$testSignals->setCache($cache);
$application->add($testSignals);

$testStrategies = new \App\Commands\TestStrategiesCommand();
$testStrategies->setCache($cache);
$application->add($testStrategies);


/**
 * Run the console app
 */
$application->run();

