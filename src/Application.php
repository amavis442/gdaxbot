<?php
require __DIR__.'/Bootstrap.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \App\Commands\SettingsCommand());


$bot = new \App\Commands\RunBotCommand();
$bot->setContainer($container);
$application->add($bot);


$application->add(new \App\Commands\ReportCommand());
$application->add(new \App\Commands\ReportOpenOrdersCommand());
$application->add(new \App\Commands\ChangeSellOrderCommand());
$application->add(new \App\Commands\ReportProfitsCommand());
$application->add(new \App\Commands\GdaxWebsocketCommand());

$ticker = new \App\Commands\RunTickerCommand();
$ticker->setContainer($container);
$application->add($ticker);

$updatePositions = new \App\Commands\RunUpdatePositionsCommand();
$updatePositions->setContainer($container);
$application->add($updatePositions);

/** 
 * For testing
 */
$application->add(new \App\Commands\TestCandlesCommand());

$application->add(new \App\Commands\TestTrendsCommand());

$application->add(new \App\Commands\TestSignalsCommand());

$application->add(new \App\Commands\TestStrategiesCommand());

$application->add(new \App\Commands\TestStoplossCommand());

/**
 * Run the console app
 */
$application->run();