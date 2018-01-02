#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/bootstrap.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \App\Commands\SettingsCommand());
$application->add(new \App\Commands\RunBotCommand());
$application->add(new \App\Commands\ReportCommand());
$application->add(new \App\Commands\ReportOpenOrdersCommand());
$application->add(new \App\Commands\ChangeSellOrderCommand());
$application->add(new \App\Commands\ReportProfitsCommand());
$application->add(new \App\Commands\GdaxWebsocketCommand());

/** 
 * For testing
 */
$application->add(new \App\Commands\TestCandlesCommand());

$application->add(new \App\Commands\TestTrendsCommand());

$application->add(new \App\Commands\TestSignalsCommand());

$application->add(new \App\Commands\TestStrategiesCommand());


/**
 * Run the console app
 */
$application->run();

