<?php

/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 02-01-18
 * Time: 15:18
 */

namespace App\Strategies;

use App\Contracts\StrategyInterface;

class TrendingLinesStrategy implements StrategyInterface
{

    protected $indictors;
    protected $orderService;
    protected $gdaxService;
    protected $config;
    protected $name;


    public function getName() : string
    {
        return get_called_class();
    }

    public function setIndicicators($indicators){
        $this->indicators =$indicators;
    }

    public function setOrderService($orderService){
        $this->orderService = $orderService;
    }

    public function setGdaxService($gdaxService){
        $this->gdaxService = $gdaxService;
    }


    public function settings(array $config = null)
    {
        $this->config = $config;
    }

    public function getSignal()
    {
        $indicators = $this->indicators;

        $instrument = 'BTC-EUR';
        $recentData = $indicators->getRecentData($instrument);
        $cci        = $indicators->cci($instrument, $recentData);
        $cmo        = $indicators->cmo($instrument, $recentData);
        $mfi        = $indicators->mfi($instrument, $recentData);

        //Trends
        $httc = $indicators->httc($instrument, $recentData); // Hilbert Transform - Trend vs Cycle Mode
        $htl  = $indicators->htl($instrument, $recentData); // Hilbert Transform - Trend vs Cycle Mode
        $hts  = $indicators->hts($instrument, $recentData);
        $mmi  = $indicators->mmi($instrument, $recentData);

        switch ($httc) {
            case 0:
                echo "httc: Cycling mode\n";
                break;
            case 1:
                echo "httc: Trending mode\n";
                break;
        }

        switch ($htl) {
            case -1:
                echo "htl: Downtrend\n";
                break;
            case 0:
                echo "htl: Hold\n";
                break;
            case 1:
                echo "htl: Uptrend\n";
                break;
        }

        switch ($hts) {
            case -1:
                echo "hts: Sell\n";
                break;
            case 0:
                echo "hts: Hold\n";
                break;
            case 1:
                echo "hts: Buy\n";
                break;
        }


        switch ($indicators->mmi($instrument, $recentData)) {     # Hilbert Transform - Trend vs Cycle Mode
            case -1:
                echo "mmi: Not trending\n";
                break;
            case 0:
                echo "mmi: Hold\n";
                break;
            case 1:
                echo "mmi: Trending\n";
                break;
        }


        /** instrument is overbought, we will short */
        if ($cci == -1 && $cmo == -1 && $mfi == -1) {
            $overbought = 1;
            echo "Overbought going Short (sell)\n";
        }

        /** It is underbought, we will go LONG */
        if ($cci == 1 && $cmo == 1 && $mfi == 1) {
            $underbought = 1;
            echo "Underbought going LONG (buy)\n";
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
            echo "adx down_cross -> buy";
            $buy = 1;
        }

        $sell = 0;
        if ($adx == 1 && $up_cross) {
            echo "adx up_cross -> sell";
            $sell = 1;
        }

        if ($httc == 1 && $htl == 1 && $mmi == 1) {
            return 'buy';
        }

        if ($httc == 1 && $htl == -1 && $mmi == 1) {
            return 'sell';
        }

        return 'hold';
    }

    /**
     * Checks if there are slots open to place a buy order an if so places x amount of orders
     *
     * @param int $overrideMaxOrders
     *
     * @return type
     */
    public function createPosition($currentPrice)
    {
        $spread      = $this->config['spread'];
        $size        = $this->config['size'];
        $max_orders  = (int)$this->config['max_orders'];

        $restOrders      = $max_orders - (int)$this->orderService->getNumOpenOrders();
        $lowestSellPrice = $this->orderService->getLowestSellPrice();
        $signal          = $this->getSignal();

        if ($signal == 'hold' || $signal == 'sell') {
            echo "-- Strategy says: " . $signal . ". So we will not buy for now.\n";

            return;
        }


        $oldBuyPrice = $currentPrice - 0.01;
        for ($i = 1; $i <= $restOrders; $i++) {
            // for buys
            $buyPrice = $oldBuyPrice - $spread;
            $buyPrice = number_format($buyPrice, 2, '.', '');

            // Check if we already have a buy for this price, then try to find an open slot
            $hasBuyPrice = $this->orderService->buyPriceExists($buyPrice);
            $n           = 1;
            $placeOrder  = true;
            while ($hasBuyPrice) {
                $buyPrice = $buyPrice - $n * $this->spread;
                $buyPrice = number_format($buyPrice, 2, '.', '');

                $hasBuyPrice = $this->orderService->buyPriceExists($buyPrice);
                if ($n > 15) {
                    $placeOrder  = false;
                    $hasBuyPrice = false;
                }
                $n++;
            }


            if ((is_null($lowestSellPrice) || $lowestSellPrice == 0 || $buyPrice < $lowestSellPrice) && $placeOrder) {
                echo 'Buy ' . $size . ' for ' . $buyPrice . "\n";

                $order = $this->gdaxService->placeLimitBuyOrder($size, $buyPrice);

                if ($order->getId() && ($order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_PENDING || $order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)) {
                    $this->orderService->insertOrder('buy', $order->getId(), $size, $buyPrice);
                } else {
                    $this->orderService->insertOrder('buy', $order->getId(), $size, $buyPrice, $order->getMessage());
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
    public function closePosition()
    {
        $sellspread = $this->config['sellspread'];


        $startPrice           = $this->gdaxService->getCurrentPrice();
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
                        $buyprice  = $row['amount'];
                        $sellPrice = $buyprice + $sellspread;
                        if ($startPrice > $sellPrice) {
                            $sellPrice = $startPrice + 0.01;
                        }
                        $sellPrice = number_format($sellPrice, 2, '.', '');

                        echo 'Sell ' . $row['size'] . ' for ' . $sellPrice . "\n";

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
