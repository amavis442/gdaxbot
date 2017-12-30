<?php

namespace App\Bot;

use Carbon\Carbon;

/**
 * Description of Gdaxbot
 *
 * @author patrick
 */
class Gdaxbot {

    protected $orderService;
    protected $gdaxService;
    protected $spread;
    protected $sellspread;
    protected $order_size;
    protected $max_orders_per_run;
    protected $waitingtime;
    protected $lifetime;
    protected $pendingBuyPrices;
    protected $bottomBuyingTreshold;
    protected $topBuyingTreshold;
    protected $botactive = false;

    /**
     * Get the number of open LTC orders 
     * Calc allowed number of order = max_orders - open orders.
     * Get the max bid price and substract 10
     */
    public function __construct(Array $settings, \App\Contracts\OrderServiceInterface $orderService, \App\Contracts\GdaxServiceInterface $gdaxService) {
        $this->orderService = $orderService;
        $this->gdaxService = $gdaxService;

        $this->max_orders_per_run = getenv('MAX_ORDERS_PER_RUN');
        $this->waitingtime = getenv('WAITINGTIME');

        $this->spread = $settings['spread'];
        $this->sellspread = $settings['sellspread'];
        $this->order_size = $settings['size'];
        $this->max_orders = $settings['max_orders'];

        $this->lifetime = $settings['lifetime'];

        $this->bottomBuyingTreshold = $settings['bottom'];
        $this->topBuyingTreshold = $settings['top'];

        $this->botactive = ($settings['botactive'] == 1 ? true : false);
    }

    /**
     * Check if we have added orders manually and add them to the database.
     */
    public function actualize() {
        $orders = $this->gdaxService->getOpenOrders();
        if (count($orders)) {
            $this->orderService->fixUnknownOrdersFromGdax($orders);
        }
    }

    /**
     * Update the open buys
     */
    public function actualizeBuys() {
        $rows = $this->orderService->getOpenBuyOrders();

        if (count($rows)) {
            foreach ($rows as $row) {
                $order = $this->gdaxService->getOrder($row['order_id']);

                if ($order->getStatus()) {
                    $this->orderService->updateOrderStatus($row['id'], $order->getStatus());
                }

                if ($order->getStatus()) {
                    $this->orderService->updateOrderStatus($row['id'], $order->getStatus());
                } else {
                    $this->orderService->updateOrderStatus($row['id'], $order->getMessage());
                }
            }
        }
    }

    /**
     * Update the open Sells
     */
    public function actualizeSells() {
        $rows = $this->orderService->getOpenSellOrders();

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $order = $this->gdaxService->getOrder($row['order_id']);

                if ($order->getStatus()) {
                    $this->orderService->updateOrderStatus($row['id'], $order->getStatus());
                }

                if ($order->getMessage() == 'NotFound') {
                    $this->orderService->updateOrderStatus($row['id'], $order->getStatus());
                }
            }
        }
    }

    /**
     * Cancel pending/open buy orders that have not filled yet in x seconds (90 seconds for now)
     */
    public function timeoutBuyOrders() {
        $currentPendingOrders = $this->orderService->getPendingBuyOrders();

        if (count($currentPendingOrders)) {
            foreach ($currentPendingOrders as $row) {

                // Get the status of the buy order. You can only sell what you got.
                $order = $this->gdaxService->getOrder($row['order_id']);

                if ($order instanceof \GDAX\Types\Response\Authenticated\Order) {
                    $status = $order->getStatus();
                } else {
                    $status = null;
                }

                // Check for old orders and if so cancel them to start over
                if ($status == 'pending' || $status == 'open') {
                    $diffInSecs = Carbon::createFromFormat('Y-m-d H:i:s', $row['created_at'])->diffInSeconds(Carbon::now());

                    if (Carbon::createFromFormat('Y-m-d H:i:s', $row['created_at'])->diffInSeconds(Carbon::now()) > $this->lifetime) {

                        $response = $this->gdaxService->cancelOrder($row['order_id']);
                        echo $response->getMessage() . "\n";

                        if (isset($response)) {
                            echo "Order " . $row['order_id'] . " is older then " . $this->lifetime . " seconds (" . $diffInSecs . ") and will be deleted\n";
                            $this->orderService->updateOrderStatus($row['id'], 'deleted');
                        } else {
                            echo "Could not cancel order " . $row['order_id'] . " for " . $row['amount'] . "\n";
                        }
                    }
                }

                if (is_null($status)) {
                    echo "Order not found with order id: " . $row['order_id'] . "\n";
                    $this->orderService->updateOrderStatus($row['id'], $order->getMessage());
                }
            }
        }
    }

    /**
     * Checks if there are slots open to place a buy order an if so places x amount of orders
     * 
     * @param int $overrideMaxOrders
     * @return type
     */
    public function buy($overrideMaxOrders = 0) {
        $restOrders = $this->max_orders - $this->orderService->getNumOpenOrders();
        $lowestSellPrice = $this->orderService->getLowestSellPrice();
        $startPrice = $this->gdaxService->getCurrentPrice();

        if (!$startPrice || $startPrice < 1 || $startPrice > $this->topBuyingTreshold || $startPrice < $this->bottomBuyingTreshold) {
            printf("Treshold reached %s  [%s]  %s so no buying for now\n", $this->bottomBuyingTreshold, $startPrice, $this->topBuyingTreshold);
            return;
        }

        if ($overrideMaxOrders > 0) {
            $restOrders = $overrideMaxOrders;
        }

        $oldBuyPrice = $startPrice - 0.01;
        for ($i = 1; $i <= $restOrders; $i++) {
            // for buys
            $buyPrice = $oldBuyPrice - $this->spread;
            $buyPrice = number_format($buyPrice, 2, '.', '');

            // Check if we already have a buy for this price, then try to find an open slot
            $hasBuyPrice = $this->orderService->buyPriceExists($buyPrice);
            $n = 1;
            $placeOrder = true;
            while ($hasBuyPrice) {
                $buyPrice = $buyPrice - $n * $this->spread;
                $buyPrice = number_format($buyPrice, 2, '.', '');

                $hasBuyPrice = $this->orderService->buyPriceExists($buyPrice);
                if ($n > 15) {
                    $placeOrder = false;
                    $hasBuyPrice = false;
                }
                $n++;
            }


            if ((is_null($lowestSellPrice) || $lowestSellPrice == 0 || $buyPrice < $lowestSellPrice) && $placeOrder) {
                echo 'Buy ' . $this->order_size . ' for ' . $buyPrice . "\n";

                $order = $this->gdaxService->placeLimitBuyOrder($this->order_size, $buyPrice);

                if ($order->getId() && ($order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_PENDING || $order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)) {
                    $this->orderService->insertOrder('buy', $order->getId(), $this->order_size, $buyPrice);
                } else {
                    $this->orderService->insertOrder('buy', $order->getId(), $this->order_size, $buyPrice, $order->getMessage());
                    echo "Order not placed for " . $buyPrice . "\n";
                }

                $oldBuyPrice = $buyPrice;
            } else {
                echo "We have open sells that will cross the buys and that is not allowed:" . $buyPrice . "\n";
            }
        }
    }

    /**
     * Checks the open buys and if they are filled then place a buy order for the same size but higher price
     */
    public function sell() {
        $startPrice = $this->gdaxService->getCurrentPrice();
        $currentPendingOrders = $this->orderService->getOpenBuyOrders();

        $n = 1;
        if (is_array($currentPendingOrders)) {
            foreach ($currentPendingOrders as $row) {
                // Get the status of the buy order. You can only sell what you got.
                $buyOrder = $this->gdaxService->getOrder($row['order_id']);

                /** \GDAX\Types\Response\Authenticated\Order $orderData */
                if ($buyOrder instanceof \GDAX\Types\Response\Authenticated\Order) {
                    $status = $buyOrder->getStatus();

                    if ($status == 'done') {
                        $buyprice = $row['amount'];
                        $sellPrice = $buyprice + $this->sellspread;
                        if ($startPrice > $sellPrice) {
                            $sellPrice = $startPrice + 0.01;
                        }
                        $sellPrice = number_format($sellPrice, 2, '.', '');

                        echo 'Sell ' . $this->order_size . ' for ' . $sellPrice . "\n";

                        $sellOrder = $this->gdaxService->placeLimitSellOrder($row['size'], $sellPrice);

                        if ($sellOrder->getId() && ($sellOrder->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_PENDING || $sellOrder->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)) {

                            $this->orderService->insertOrder('sell', $sellOrder->getId(), $row['size'], $sellPrice, 'open', $row['id']);

                            echo "Updating order status from pending to done: " . $row['order_id'] . "\n";
                            $this->orderService->updateOrderStatus($row['id'], $status);
                        } else {
                            $this->orderService->insertOrder('sell', $sellOrder->getId(), $row['size'], $sellPrice, $sellOrder->getMessage(), $row['id']);
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
     * Main entry point
     */
    public function run() {
        if ($this->botactive) {
            echo "Delete orders without order id\n";
            $this->orderService->garbageCollection();

            echo "Check gdax exchange with database for orders in gdax but not in database\n";
            $this->actualize();

            echo "Check gdax if sells have changed status from open to filled\n";
            $this->actualizeSells();

            echo "Fix rejected sells so we can sell them\n";
            $this->orderService->fixRejectedSells();

            echo "Place sell orders\n";
            $this->sell();

            echo "Check gdax if buys have changed status from open to filled\n";
            $this->actualizeBuys();

            echo "A buy order has x seconds to complete before removed and new buy is placed\n";
            $this->timeoutBuyOrders();

            echo "Place buy orders\n";
            $this->buy();


            echo "\nDONE " . date('Y-m-d H:i:s') . "\n";
        } else {
            echo "Bot is not active at the moment\n";
        }
    }

}
