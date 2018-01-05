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
class RunBotCommand extends Command
{

    use ActualizeBuysAndSells, OHLC;

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

    protected function configure()
    {
        $this->setName('bot:run')
             ->setDescription('Runs the bot for 1 cycle use cron to call this command.')
             ->addOption('sandbox', null, InputOption::VALUE_NONE, 'Run bot in sandbox so no real trades will be made.')
             ->setHelp('Runs the bot for 1 cycle use cron to call this command.');
    }

    protected function getStrategy()
    {
        /**
         * Available strategy's
         */
        $strategies = [
            'Trendlines' => new \App\Strategies\TrendingLinesStrategy(),
        ];

        return $strategies['Trendlines'];
    }

    protected function getRule($ruleName)
    {
        $rules = [
            'PriceIsRight' => new \App\Rules\PriceIsRightRule(),
        ];

        return $rules[$ruleName];
    }


    protected function createPosition($size, $price, $takeProfitAt, $strategyName = ''): bool
    {
        $positionCreated = false;

        $order = $this->gdaxService->placeLimitBuyOrder($size, $price);

        if ($order->getId() && ($order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_PENDING || $order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)) {
            $this->orderService->insertOrder('buy', $order->getId(), $size, $price, $strategyName, $takeProfitAt);
            $positionCreated = true;
        } else {
            $reason = $order->getMessage() . $order->getRejectReason() . ' ';
            $this->orderService->insertOrder('buy', $order->getId(), $size, $price, $strategyName, 0.0, 0, 0, $reason);
        }

        return $positionCreated;
    }


    /**
     * Checks the open buys and if they are filled then place a buy order for the same size but higher price
     */
    protected function closePosition($strategyName = '')
    {
        $currentPendingOrders = $this->orderService->getOpenBuyOrders();

        if (is_array($currentPendingOrders)) {
            foreach ($currentPendingOrders as $row) {
                // Get the status of the buy order. You can only sell what you got.
                $buyOrder = $this->gdaxService->getOrder($row['order_id']);

                /** \GDAX\Types\Response\Authenticated\Order $orderData */
                if ($buyOrder instanceof \GDAX\Types\Response\Authenticated\Order) {
                    $status = $buyOrder->getStatus();

                    if ($status == 'done') {
                        $size      = $row['size'];
                        $sellPrice = $row['take_profit'];
                        $sellPrice = number_format($sellPrice, 2, '.', '');
                        $parent_id = $row['id'];


                        echo 'Sell at: ' . $sellPrice . "\n";
                        echo 'Sell size: ' . $row['size'] . "\n";

                        $sellOrder = $this->gdaxService->placeLimitSellOrder($size, $sellPrice);

                        if ($sellOrder->getId() && ($sellOrder->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_PENDING || $sellOrder->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)) {

                            $this->orderService->insertOrder('sell', $sellOrder->getId(), $size, $sellPrice, $strategyName, 0.0, 0, 0, 'open', $parent_id);

                            echo "Updating order status from pending to done: " . $row['order_id'] . "\n";
                            $this->orderService->updateOrderStatus($row['id'], $status);
                        } else {
                            $this->orderService->insertOrder('sell', $sellOrder->getId(), $size, $sellPrice, $strategyName, 0.0, 0, 0, $sellOrder->getMessage(), $parent_id);
                        }
                    } else {
                        echo "Order not done " . $row['order_id'] . "\n";
                    }
                } else {
                    echo "Order sell not done because " . $buyOrder->getMessage() . "\n";
                }
            }
        }
    }

    /**
     * Experimental stoploss (proof of concept)
     */
    protected function stopLoss(string $signal, float $currentPrice)
    {

        $sellOrders = $this->orderService->getOpenSellOrders();

        if (is_array($sellOrders) && count($sellOrders)) {
            foreach ($sellOrders as $sellOrder) {
                $buyId    = $sellOrder['parent_id'];
                $buyOrder = $this->orderService->fetchOrder($buyId);

                if (!$buyOrder) {
                    echo "Buyorder not found for " . $sellOrder['order_id'] . "\n";
                    continue;
                }


                $take_profit  = $buyOrder->amount + 20;
                $newSellPrice = $currentPrice - 20;

                $oldSellPrice = $sellOrder['amount'];
                $buyPrice     = $buyOrder->amount;

                $oldSellPrice = Cache::get($buyOrder->order_id);

                printf("WIP SL: == CurrentPrice: %s, BuyPrice: %s, Signal: %s\n", $currentPrice, $buyPrice, $signal);
                if ($signal == 'buy' && $currentPrice < $buyPrice) {
                    $oldSellPrice = $take_profit;
                    echo "We are comming from a loss and it goes back up again: " . $take_profit . "\n";
                }

                //trailing sell order upwards
                if ($currentPrice >= $take_profit && $oldSellPrice < $newSellPrice) {
                    // Stoploss
                    echo "Take profit price would be: " . $newSellPrice . "\n";
                    // Steps cancel old sellprice and place new sell order.

                    Cache::put($buyOrder->order_id, $newSellPrice, 360);
                }

                $take_loss = $buyOrder->amount - 20;
                //trailing sell order
                if ($signal == 'sell' && $currentPrice > $take_loss && $currentPrice < $buyPrice) {
                    // Stoploss
                    echo "Take loss price would be: " . $newSellPrice . "\n";
                    // Steps cancel old sellprice and place new sell order.
                }
                echo "****\n\n";


            }
        }
    }


    protected function updateTicker($pair = 'BTC-EUR')
    {
        $httpClient = new \GuzzleHttp\Client();

        // Ticker
        $res = $httpClient->request('GET', 'https://api.gdax.com/products/' . $pair . '/ticker');

        if ($res->getStatusCode() == 200) {
            $jsonData = $res->getBody();
            $data     = json_decode($jsonData, true);

            $ticker               = [];
            $ticker['product_id'] = $pair;
            $ticker['timeid']     = (int)\Carbon\Carbon::parse($data['time'])->setTimezone('Europe/Amsterdam')->format('YmdHis');
            $ticker['volume']     = (int)round($data['volume']);
            $ticker['price']      = (float)number_format($data['price'], 2, '.', '');

            $this->markOHLC($ticker);
        }
    }


    protected function init($sandbox = false)
    {
        $this->settingsService = new \App\Services\SettingsService();
        $this->orderService    = new \App\Services\OrderService();
        $this->gdaxService     = new \App\Services\GDaxService();

        $this->gdaxService->setCoin(getenv('CRYPTOCOIN'));

        $this->gdaxService->connect($sandbox);
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sandbox = false;
        if ($input->getOption('sandbox')) {
            $output->writeln('<info>Running in sandbox mode</info>');
            $sandbox = true;
        }

        $this->init($sandbox);


        while (1) {
            $output->writeln("=== RUN [" . \Carbon\Carbon::now('Europe/Amsterdam')->format('Y-m-d H:i:s') . "] ===");

            $output->writeln(" Update Ticker ");
            $this->updateTicker();

            // Settings
            $config                       = [];
            $config['max_orders_per_run'] = getenv('MAX_ORDERS_PER_RUN');
            $config                       = array_merge($config, $this->settingsService->getSettings());

            $spread = $config['spread'];

            //Cleanup
            $this->orderService->garbageCollection();
            $this->actualize();
            $this->actualizeSells();
            $this->orderService->fixRejectedSells();

            // Now we can use strategy
            /** @var \App\Contracts\StrategyInterface $strategy */
            $strategy = $this->getStrategy();

            // Even when the limit is reached, i want to know the signal
            $signal = $strategy->getSignal();
            $output->writeln("Signal: " . $signal);

            $numOpenOrders        = (int)$this->orderService->getNumOpenOrders();
            $numOrdersLeftToPlace = (int)$config['max_orders'] - $numOpenOrders;
            if (!$numOrdersLeftToPlace) {
                $numOrdersLeftToPlace = 0;
            }


            $botactive = ($config['botactive'] == 1 ? true : false);
            if (!$botactive) {
                $output->writeln("<info>Bot is not active at the moment</info>");
            } else {

                $currentPrice = $this->gdaxService->getCurrentPrice();
                Cache::put('BTC-EUR.currentPrice', $currentPrice);

                // WIP
                $this->stopLoss($signal, $currentPrice);

                // Create safe limits
                $topLimit    = $config['top'];
                $bottomLimit = $config['bottom'];

                if (!$currentPrice || $currentPrice < 1 || $currentPrice > $topLimit || $currentPrice < $bottomLimit) {
                    $output->writeln(sprintf("<info>Treshold reached %s  [%s]  %s so no buying for now</info>", $bottomLimit, $currentPrice, $topLimit));
                } else {

                    $output->writeln("** Place sell orders");
                    $this->closePosition($strategy->getName());

                    $this->actualizeBuys();


                    if ($signal == PositionConstants::BUY && $numOrdersLeftToPlace > 0) {
                        $output->writeln("** Place buy orders");

                        $profit = $config['sellspread'];
                        $size   = $config['size'];

                        // Determine the price we want it
                        $buyPrice     = number_format($currentPrice - 0.01, 2, '.', '');
                        $takeProfitAt = number_format($buyPrice + $profit, 2, '.', '');

                        // Price should go up buy 30 euro to place next one
                        $lowestSellPrice = $this->orderService->getBottomOpenSellOrder();
                        $highestBuyPrice = $this->orderService->getTopOpenBuyOrder();
                        $lowestBuyPrice  = $this->orderService->getBottomOpenBuyOrder();


                        $rulePriceIsRight = $this->getRule('PriceIsRight');
                        $canPlaceBuyOrder = $rulePriceIsRight->validate($buyPrice, $spread, $lowestBuyPrice, $highestBuyPrice, $lowestSellPrice, null);


                        if ($canPlaceBuyOrder) {
                            if ($this->createPosition($size, $buyPrice, $takeProfitAt, $strategy->getName())) {
                                $output->writeln('Position created: ' . $size . ' ' . $currentPrice . ' Take profit At ' . $takeProfitAt);
                            } else {
                                $output->writeln('<warning>Failed to create position created: ' . $size . ' ' . $currentPrice . ' Take profit At' . $takeProfitAt . '</warning>');
                            }
                        }
                    }
                    $output->writeln("=== DONE " . date('Y-m-d H:i:s') . " ===");
                }
            }

            sleep(10);
        }
    }
}
