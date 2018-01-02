<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 02-01-18
 * Time: 15:18
 */

namespace App\Strategies\Traits;


trait TrendingLinesStrategy
{
    public function getStrategy()
    {
        $indicators = $this->indicators;

        $instrument = 'BTC-EUR';
        $recentData = $indicators->getRecentData($instrument);
        $cci        = $indicators->cci($instrument, $recentData);
        $cmo        = $indicators->cmo($instrument, $recentData);
        $mfi        = $indicators->mfi($instrument, $recentData);

        switch ($indicators->httc($instrument, $recentData)) {     # Hilbert Transform - Trend vs Cycle Mode
            case 0:
                $httc = 'Cycling mode';
                break;
            case 1:
                $httc = 'Trending mode';
                break;
        }

        switch ($indicators->htl($instrument, $recentData)) {     # Hilbert Transform - Trend vs Cycle Mode
            case -1:
                $htl = 'Downtrend';
                break;
            case 0:
                $htl = 'Hold';
                break;
            case 1:
                $htl = 'Uptrend';
                break;
        }

        switch ($indicators->hts($instrument, $recentData)) {
            case -1:
                $hts = 'Sell';
                break;
            case 0:
                $hts = 'Hold';
                break;
            case 1:
                $hts = 'Buy';
                break;
        }


        switch ($indicators->mmi($instrument, $recentData)) {     # Hilbert Transform - Trend vs Cycle Mode
            case -1:
                $mmi = 'Not trending';
                break;
            case 0:
                $mmi = 'Hold';
                break;
            case 1:
                $mmi = 'Trending';
                break;
        }


        /** instrument is overbought, we will short */
        if ($cci == -1 && $cmo == -1 && $mfi == -1) {
            $overbought = 1;
            echo "Overbought \n";
        }

        /** It is underbought, we will go LONG */
        if ($cci == 1 && $cmo == 1 && $mfi == 1) {
            $underbought = 1;
            echo "Underbought \n";
        }

        $adx         = $indicators->adx($instrument, $recentData);
        $_sma6       = trader_sma($recentData['close'], 6);
        $sma6        = array_pop($_sma6);
        $prior_sma6  = array_pop($_sma6);
        $_sma40      = trader_sma($recentData['close'], 40);
        $sma40       = array_pop($_sma40);
        $prior_sma40 = array_pop($_sma40);

        /** have the lines crossed? */
        $down_cross = (($prior_sma6 <= $sma40 && $sma6 > $sma40) ? 1 : 0);
        $up_cross   = (($prior_sma40 <= $sma6 && $sma40 > $sma6) ? 1 : 0);

        $buy = 0;
        if ($adx == 1 && $down_cross) {
            $buy = 1;
        }
        $sell = 0;
        if ($adx == 1 && $up_cross) {
            $sell = 1;
        }


        if ($httc == 'Trending mode' && $htl == 'Uptrend' && $mmi == 'Trending') {
            return 'buy';
        } else {
            if ($httc == 'Trending mode' && $htl == 'Downtrend' && $mmi == 'Trending') {
                return 'sell';
            } else {
                return 'hold';
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
    public function buy($overrideMaxOrders = 0, $strategy = 'hold') {

        $restOrders = $this->max_orders - $this->orderService->getNumOpenOrders();
        $lowestSellPrice = $this->orderService->getLowestSellPrice();
        $startPrice = $this->gdaxService->getCurrentPrice();


        if ($strategy == 'hold' || $strategy == 'sell') {
            echo "Strategy says: ". $strategy. ". So we will not buy for now.";
            return;
        }

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
}