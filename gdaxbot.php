#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/bootstrap.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \App\Commands\SettingsCommand());
// ... register commands

$application->run();

