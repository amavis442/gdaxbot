#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/bootstrap.php';

use Symfony\Component\Console\Application;

$application = new Application();

$c = new \App\Commands\SettingsCommand();
$application->add($c);
$c->setConn($conn);
// ... register commands

$application->run();

