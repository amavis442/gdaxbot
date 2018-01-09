<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Commands;

use App\Util\Indicators;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use App\Strategies\Traits\TrendingLinesStrategy;
use App\Util\Cache;
use App\Traits\OHLC;
use App\Util\PositionConstants;

/**
 * Description of RunBotCommand
 *
 * @author patrick
 */
class RunBotCommand extends Command
{

    use OHLC;

    /**
     * @var \App\Contracts\GdaxServiceInterface
     */
    protected $gdaxService;

    /**
     * @var \App\Contracts\OrderServiceInterface;
     */
    protected $orderService;

    /**
     * @var \App\Contracts\StrategyInterface;
     */
    protected $settingsService;
    protected $httpClient;
    protected $container;


    public function setContainer($container)
    {
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setName('bot:run:buys')
             ->setDescription('Runs the bot for 1 cycle use cron to call this command.')
             ->addOption('test', null, InputOption::VALUE_NONE, 'Run bot, but is will not open an/or close positions but it will update the database so please use a _dev database.')
             ->setHelp('Runs the bot for 1 cycle use cron to call this command.');
    }

    /**
     * Factory
     *
     * @return \App\Commands\active
     */
    protected function getStrategy()
    {
        return $this->container->get('bot.strategy');
    }

    protected function getRule($side)
    {
        return $this->container->get('bot.' . $side . '.rule');
    }

    protected function init()
    {
        $this->settingsService = $this->container->get('bot.settings');
        $this->orderService    = $this->container->get('bot.service.order');
        $this->gdaxService     = $this->container->get('bot.service.gdax');
        $this->httpClient      = $this->container->get('bot.httpclient');
        $this->positionService = $this->container->get('bot.service.position');
    }

    protected function placeBuyOrder($size, $price): bool
    {
        $positionCreated = false;

        $order = $this->gdaxService->placeLimitBuyOrder($size, $price);

        if ($order->getId() && ($order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_PENDING || $order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)) {
            $this->orderService->buy($order->getId(), $size, $price);
            $positionCreated = true;
        } else {
            $reason = $order->getMessage() . $order->getRejectReason() . ' ';
            $this->orderService->insertOrder('buy', 'rejected', $size, $price, $reason);
        }

        return $positionCreated;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init();

        // Get Account
        $account = $this->gdaxService->getAccount('EUR');

        // Now we can use strategy
        /** @var \App\Contracts\StrategyInterface $strategy */
        $strategy = $this->getStrategy();
        $buyRule  = $this->getRule('buy');

        while (1) {
            $output->writeln("=== RUN [" . \Carbon\Carbon::now('Europe/Amsterdam')->format('Y-m-d H:i:s') . "] ===");
            // Settings
            $config                       = [];
            $config['max_orders_per_run'] = getenv('MAX_ORDERS_PER_RUN');
            $config                       = array_merge($config, $this->settingsService->getSettings());

            // Even when the limit is reached, i want to know the signal
            $signal = $strategy->getSignal();
            $output->writeln("Signal: " . $signal);

            $numOpenOrders        = (int)$this->positionService->getNumOpen();
            $numOrdersLeftToPlace = (int)$config['max_orders'] - $numOpenOrders;
            if (!$numOrdersLeftToPlace) {
                $numOrdersLeftToPlace = 0;
            }

            $botactive = ($config['botactive'] == 1 ? true : false);
            if (!$botactive) {
                $output->writeln("<info>Bot is not active at the moment</info>");
            } else {

                $currentPrice = $this->gdaxService->getCurrentPrice();

                // Create safe limits
                $topLimit    = $config['top'];
                $bottomLimit = $config['bottom'];

                if (!$currentPrice || $currentPrice < 1 || $currentPrice > $topLimit || $currentPrice < $bottomLimit) {
                    $output->writeln(sprintf("<info>Treshold reached %s  [%s]  %s so no buying for now</info>", $bottomLimit, $currentPrice, $topLimit));
                } else {
                    if ($signal == PositionConstants::BUY && $numOrdersLeftToPlace > 0) {

                        $size     = $config['size'];
                        $buyPrice = number_format($currentPrice - 0.01, 2, '.', '');
                        $output->writeln("Place buyorder for size " . $size . ' and price ' . $buyPrice);
                        $this->placeBuyOrder($size, $buyPrice);
                    }
                    $output->writeln("=== DONE " . date('Y-m-d H:i:s') . " ===");
                }
            }

            sleep(30);
        }
    }
}
