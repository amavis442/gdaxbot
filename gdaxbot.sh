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



$application->run();

