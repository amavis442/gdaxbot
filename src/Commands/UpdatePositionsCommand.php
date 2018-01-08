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
use App\Traits\Positions;
use App\Traits\ActualizeBuysAndSells;
use App\Strategies\Traits\TrendingLinesStrategy;
use App\Util\Cache;
use App\Traits\OHLC;
use App\Util\PositionConstants;

/**
 * Description of RunBotCommand
 *
 * @author patrick
 */
class UpdatePositionsCommand extends Command
{

    use OHLC;

    protected $testMode = false;

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
    protected $positionService;
    protected $stoplossRule;

    public function setContainer($container)
    {
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setName('bot:run:update')
            ->setDescription('Update the positions.')
            ->addOption('test', null, InputOption::VALUE_NONE, 'Run bot, but is will not open an/or close positions but it will update the database so please use a _dev database.')
            ->setHelp('Runs the bot for 1 cycle use cron to call this command.');
    }

    protected function init()
    {
        $this->settingsService = $this->container->get('bot.settings');
        $this->orderService = $this->container->get('bot.service.order');
        $this->gdaxService = $this->container->get('bot.service.gdax');
        $this->positionService = $this->container->get('bot.service.position');
        $this->stoplossRule = $this->container->get('bot.rule.stoploss');
    }

    /**
     * Check if we have added orders manually and add them to the database.
     */
    public function actualize()
    {
        $orders = $this->gdaxService->getOpenOrders();
        if (count($orders)) {
            $this->orderService->fixUnknownOrdersFromGdax($orders);
        }
    }

    /**
     * Update the open buys
     */
    public function updateBuyOrderStatusAndCreatePosition()
    {
        $orders = $this->orderService->getOpenBuyOrders();

        if (count($orders)) {
            foreach ($orders as $order) {
                $gdaxOrder = $this->gdaxService->getOrder($order['order_id']);
                $position_id = 0;
                $status = $gdaxOrder->getStatus();

                if ($status) {
                    if ($status == 'done') {
                        $position_id = $this->positionService->open($gdaxOrder->getId(), $gdaxOrder->getSize(), $gdaxOrder->getPrice());
                    }

                    $this->orderService->updateOrderStatus($order['id'], $order->getStatus(), $position_id);

                } else {
                    $this->orderService->updateOrderStatus($order['id'], $order->getMessage(), $position_id);
                }
            }
        }
    }

    /**
     * Update the open Sells
     */
    public function actualizeSellOrders()
    {
        $orders = $this->orderService->getOpenSellOrders();

        if (is_array($orders)) {
            foreach ($orders as $order) {
                $gdaxOrder = $this->gdaxService->getOrder($order['order_id']);
                $status = $order->getStatus();

                if ($status) {
                    $this->orderService->updateOrderStatus($order['id'], $gdaxOrder->getStatus());
                } else {
                    $this->orderService->updateOrderStatus($order['id'], $gdaxOrder->getMessage());
                }
            }
        }
    }

    public function actualizePositions()
    {
        $positions = $this->positionService->getOpen();
        if (is_array($positions)) {
            foreach ($positions as $position) {
                $position_id = $position['id'];
                $order = $this->orderService->fetchPosition($position_id,'sell');
                if ($order) {
                    $status = $order->getStatus();
                    if ($status == 'done') {
                        $this->positionService->close($position_id);
                    }
                }
            }
        }
    }

    /**
     * Checks the open buys and if they are filled then place a buy order for the same size but higher price
     */
    protected function watchPositions(float $currentPrice, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        $positions = $this->positionService->getOpen();

        if (is_array($positions)) {
            foreach ($positions as $position) {
                $price       = $position['amount'];
                $size        = $position['size'];
                $position_id = $position['id'];
                $order_id    = $position['order_id']; // Buy order_id

                $sellMe = $this->stoplossRule->trailingStop($position_id, $currentPrice, $price, getenv('STOPLOSS'), $output);

                $placeOrder = true;
                if ($sellMe) {
                    $buyOrder  = $this->orderService->fetchOrderByOrderId($order_id);
                    $parent_id = $buyOrder->id;

                    // Check if there are sell order for this position and cancel them.
                    $existingSellOrder = $this->orderService->fetchOrderByParentId($parent_id);

                    if ($existingSellOrder) {
                        // Give the order 1 minute to complete
                        $created_at = $existingSellOrder->created_at;
                        if (\Carbon\Carbon::parse('Y-m-d H:i:s', $created_at)->addMinute(1)->format('YmdHis') < \Carbon\Carbon::now()->format('YmdHis')) {
                            $placeOrder = true;
                        } else {
                            $placeOrder = false;
                        }
                    }

                    if ($placeOrder) {
                        $sellPrice = number_format($currentPrice + 0.01, 2, '.', '');

                        $order     = $this->gdaxService->placeLimitSellOrderFor1Minute($size, $sellPrice);

                        if ($order->getMessage()) {
                            $status = $order->getMessage();
                        } else {
                            $status = $order->getStatus();
                        }

                        if ($status == 'open' || $status == 'pending') {
                            $this->orderService->sell($order->getId(), $size, $price, $status, $position_id, $parent_id);
                            echo ">> Place sell order " . $order->getId() . " for position " . $position_id . "\n";
                        }
                    }
                }
            }
        }
    }


    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init();

        // Get Account
        //$account = $this->gdaxService->getAccount('EUR');

        while (1) {
            $output->writeln("=== RUN [" . \Carbon\Carbon::now('Europe/Amsterdam')->format('Y-m-d H:i:s') . "] ===");
            // Settings
            $config = [];
            $config = array_merge($config, $this->settingsService->getSettings());
           
            //Cleanup
            $this->orderService->garbageCollection();

            $this->updateBuyOrderStatusAndCreatePosition();
            $this->actualizeSellOrders();
            $this->actualizePositions();

            $botactive = ($config['botactive'] == 1 ? true : false);
            if (!$botactive) {
                $output->writeln("<info>Bot is not active at the moment</info>");
            } else {
                $currentPrice = $this->gdaxService->getCurrentPrice();

                $output->writeln("** Update positions");

                $this->watchPosition($currentPrice, $output);

                $output->writeln("=== DONE " . date('Y-m-d H:i:s') . " ===");
            }

            $this->actualizeSellOrders();
            $this->actualizePositions();
            //$this->actualize();

            sleep(2);
        }
    }
}
