<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 09-01-18
 * Time: 17:29
 */

$container = new ContainerBuilder();

$container->register('bot.strategy', $availableStrategies[$activeStrategy]);
$container->register('bot.buy.rule', $availableRules[$activeBuyRule]);
$container->register('bot.sell.rule', $availableRules[$activeSellRule]);

$container->register('bot.settings', '\App\Services\SettingsService');
$container->register('bot.service.order', '\App\Services\OrderService');
$container->register('bot.service.position', '\App\Services\PositionService');
$container->register('bot.service.gdax', '\App\Services\GDaxService')
          ->addMethodCall('setCoin', [getenv('CRYPTOCOIN')])
          ->addMethodCall('connect', [$sandbox]);
$container->register('bot.httpclient', '\GuzzleHttp\Client');
$container->register('bot.rule.stoploss', '\App\Rules\Stoploss');