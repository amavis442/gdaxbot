<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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

$container->register('logger', '\Monolog\Logger')->addArgument('gdaxbot')
    ->addMethodCall('pushHandler',[new \Monolog\Handler\StreamHandler(__DIR__.'/../logs/gdaxlog.log', \Monolog\Logger::DEBUG)]);


// Indicators
$container->register('adx', '\App\Indicators\AverageDirectionalMovementIndexIndicator');
$container->register('atr', '\App\Indicators\AverageTrueRangeIndicator');
$container->register('cci', '\App\Indicators\CommodityChannelIndexIndicator');
$container->register('cmo', '\App\Indicators\ChandeMomentumOscillatorIndicator');
$container->register('htl', '\App\Indicators\HilbertTransformInstantaneousTrendlineIndicator');
$container->register('hts', '\App\Indicators\HilbertTransformSinewaveIndicator');
$container->register('httc', '\App\Indicators\HilbertTransformTrendVersusCycleModeIndicator');
$container->register('mmi', '\App\Indicators\MarketMeannessIndexIndicator');
$container->register('mfi', '\App\Indicators\MoneyFlowIndexIndicator');
$container->register('macd', '\App\Indicators\MovingAverageCrossoverDivergenceIndicator');
$container->register('macdext', '\App\Indicators\MovingAverageCrossoverDivergenceWithControllableMovingAverageTypeIndicator');
$container->register('obv', '\App\Indicators\OnBalanceVolumeIndicator');


$container->register('manager.indicators', '\App\Managers\IndicatorManager')
    ->addMethodCall('add', ['adx', new Reference('adx')])
    ->addMethodCall('add', ['atr', new Reference('atr')])
    ->addMethodCall('add', ['cci', new Reference('cci')])
    ->addMethodCall('add', ['cmo', new Reference('cmo')])
    ->addMethodCall('add', ['htl', new Reference('htl')])
    ->addMethodCall('add', ['hts', new Reference('hts')])
    ->addMethodCall('add', ['httc', new Reference('httc')])
    ->addMethodCall('add', ['mmi', new Reference('mmi')])
    ->addMethodCall('add', ['mfi', new Reference('mfi')])
    ->addMethodCall('add', ['macd', new Reference('macd')])
    ->addMethodCall('add', ['macdext', new Reference('macdext')])
    ->addMethodCall('add', ['obv', new Reference('obv')]);


// Strategies
$container->register('trendlines', '\App\Strategies\Trendlines');

$container->register('manager.strategy', '\App\Managers\StrategyManager')
    ->addMethodCall('add', ['trendlines', new Reference('trendlines')]);