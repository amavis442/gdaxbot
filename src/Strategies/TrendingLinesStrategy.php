<?php

/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 02-01-18
 * Time: 15:18
 */

namespace App\Strategies;

use App\Contracts\StrategyInterface;

/**
 * Class TrendingLinesStrategy
 *
 *
 * @see     https://www.quantopian.com/posts/trading-on-multiple-ta-lib-signals
 * @see     https://www.quantopian.com/posts/stocks-on-the-move-by-andreas-clenow
 *
 * @see     http://stockcharts.com/school/doku.php?id=chart_school:technical_indicators:money_flow_index_mfi
 * @see     https://tradingsim.com/blog/chande-momentum-oscillator-cmo-technical-indicator/
 * @see     http://stockcharts.com/school/doku.php?id=chart_school:technical_indicators:commodity_channel_index_cci
 *
 * @package App\Strategies
 *
 */
class TrendingLinesStrategy implements StrategyInterface
{

    /** @var \App\Util\Indicators */
    protected $indicators;

    /** @var \App\Contracts\OrderServiceInterface */
    protected $orderService;

    /** @var \App\Contracts\GdaxServiceInterface */
    protected $gdaxService;

    /** @var array */
    protected $config;

    /** @var string */
    protected $name;

    public function getName(): string
    {
        return 'TrendingLines';
    }

    public function setIndicicators($indicators)
    {
        $this->indicators = $indicators;
    }

    public function setOrderService($orderService)
    {
        $this->orderService = $orderService;
    }

    public function setGdaxService($gdaxService)
    {
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

        /**
         * Commodity channel index (cci)
         * The Commodity Channel Index (CCI) is a versatile indicator that can be used to identify a new trend or warn of extreme conditions.
         */
        $cci = $indicators->cci($recentData);

        /**
         * Chande momentum oscillator (cmo)
         * The chande momentum oscillator (CMO) was developed by Tushar Chande and is a technical indicator that attempts to capture the momentum of a security.
         */
        $cmo = $indicators->cmo($recentData);

        /**
         * Money flow index (mfi)
         * The Money Flow Index (MFI) is an oscillator that uses both price and volume to measure buying and selling pressure.
         */
        $mfi = $indicators->mfi($recentData);

        //Trends
        /**
         * Hilbert Transform - Trend vs Cycle Mode — Simply tell us if the market is
         * either trending or cycling, with an additional parameter the method returns
         * the number of days we have been in a trend or a cycle.
         */
        $httc = $indicators->httc($recentData);

        /**
         * Hilbert Transform - Instantaneous Trendline — smoothed trendline, if the
         * price moves 1.5% away from the trendline we can declare a trend.
         */
        $htl = $indicators->htl($recentData);

        /**
         * Hilbert Transform - Sinewave (MESA indicator)— We are actually using DSP
         * on the prices to attempt to get a lag-free/low-lag indicator.
         * This indicator can be passed an extra parameter and it will tell you in
         * we are in a trend or not. (when used as an indicator do not use in a trending market)
         */
        $hts = $indicators->hts($recentData);

        /**
         * Market Meanness Index (link) — This indicator is not a measure of how
         * grumpy the market is, it shows if we are currently in or out of a trend
         * based on price reverting to the mean.
         */
        $mmi = $indicators->mmi($recentData);

        switch ($httc) {
            case 0:
                echo "..httc: Cycling mode\n";
                break;
            case 1:
                echo "..httc: Trending mode\n";
                break;
        }

        switch ($htl) {
            case -1:
                echo "..htl: Downtrend\n";
                break;
            case 0:
                echo "..htl: Hold\n";
                break;
            case 1:
                echo "..htl: Uptrend\n";
                break;
        }


        switch ($hts) {
            case -1:
                echo "..hts: Sell (Only usefull when not trending)\n";
                break;
            case 0:
                echo "..hts: Hold (Only usefull when not trending)\n";
                break;
            case 1:
                echo "..hts: Buy (Only usefull when not trending)\n";
                break;
        }


        switch ($mmi) {     # Hilbert Transform - Trend vs Cycle Mode
            case -1:
                echo "..mmi: Not trending\n";
                break;
            case 0:
                echo "..mmi: Hold\n";
                break;
            case 1:
                echo "..mmi: Trending\n";
                break;
        }


        /** instrument is overbought, we will short */
        if ($cci == -1 && $cmo == -1 && $mfi == -1) {
            echo "..Overbought going Short (sell)\n";
        }

        /** It is underbought, we will go LONG */
        if ($cci == 1 && $cmo == 1 && $mfi == 1) {
            echo "..Underbought going LONG (buy)\n";
        }

        $adx         = $indicators->adx($recentData);
        $_sma6       = trader_sma($recentData['close'], 6);
        $sma6        = array_pop($_sma6);
        $prior_sma6  = array_pop($_sma6);
        $_sma40      = trader_sma($recentData['close'], 40);
        $sma40       = array_pop($_sma40);
        $prior_sma40 = array_pop($_sma40);

        /** have the lines crossed? */
        $down_cross = (($prior_sma6 <= $sma40 && $sma6 > $sma40) ? 1 : 0);
        $up_cross   = (($prior_sma40 <= $sma6 && $sma40 > $sma6) ? 1 : 0);

        if ($adx == 1 && $down_cross) {
            echo "..adx down_cross -> buy";
        }

        if ($adx == 1 && $up_cross) {
            echo "..adx up_cross -> sell";
        }

        // Check what On Balance Volume (OBV) does
        $obv = $indicators->obv($recentData);
        if ($obv == 1) {
            echo "..On Balance Volume (OBV): Upwards (buy)\n";
        }

        if ($obv == 0) {
            echo "..On Balance Volume (OBV): Hold\n";
        }

        if ($obv == -1) {
            echo "..On Balance Volume (OBV): Downwards (sell)\n";
        }

        if ($httc == 1 && $htl == 1 && $mmi == 1 && $obv == 1) {
            return 'buy';
        }

        if ($httc == 1 && $htl == -1 && $mmi == 1 && $obv == -1) {
            return 'sell';
        }

        return 'hold';
    }

    /**
     * Experimental stoploss (proof of concept)
     */
    public function stopLoss(string $signal, float $currentPrice)
    {
        $sellOrders = $this->orderService->getOpenSellOrders();
        
        if (is_array($sellOrders) && count($sellOrders)) {
            foreach ($sellOrders as $sellOrder) {
                $buyId    = $sellOrder['parent_id'];
                $buyOrder = $this->orderService->fetchOrder($buyId);

                $take_profit  = $buyOrder->amount + 20;
                $newSellPrice = $currentPrice - 20;
                $oldSellPrice = $sellOrder['amount'];
                $buyPrice     = $buyOrder->amount;

                printf("== CurrentPrice: %s, BuyPrice: %s, Signal: %s\n", $currentPrice, $buyPrice, $signal);
                if ($signal == 'buy' && $currentPrice < $buyPrice) {
                    $oldSellPrice = $take_profit;
                    echo "We are comming from a loss and it goed back up again: " . $take_profit . "\n";
                }

                //trailing sell order upwards
                if ($signal == 'buy' && $currentPrice >= $take_profit && $oldSellPrice < $newSellPrice) {
                    // Stoploss
                    echo "Take profit price would be: " . $newSellPrice . "\n";
                    // Steps cancel old sellprice and place new sell order.
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

    /**
     * Checks if there are slots open to place a buy order an if so places x amount of orders
     *
     * @param int $overrideMaxOrders
     *
     * @return type
     */
    public function createPosition($currentPrice)
    {
        $spread     = $this->config['spread'];
        $size       = $this->config['size'];
        $max_orders = (int) $this->config['max_orders'];
        $profit     = $this->config['sellspread'];

        $restOrders      = $max_orders - (int) $this->orderService->getNumOpenOrders();
        $lowestSellPrice = $this->orderService->getLowestSellPrice();
        $signal          = $this->getSignal();

        echo "-- Strategy: " . $signal . "\n";
        if ($signal == 'hold' || $signal == 'sell') {
            echo "Not buying at the moment.\n";

            return;
        }

        if ($restOrders < 1) {
            echo "-- Reached number of allowed orders: " . $max_orders . "\n";

            return;
        }

        $oldBuyPrice = $currentPrice - 0.02;
        for ($i = 0; $i < $restOrders; $i++) {
            // for buys
            $buyPrice = $oldBuyPrice - ($i * $spread);
            $buyPrice = number_format($buyPrice, 2, '.', '');

            // Check if we already have a buy for this price, then try to find an open slot
            $hasBuyPrice = $this->orderService->buyPriceExists($buyPrice);
            $n           = 1;
            $placeOrder  = true;
            while ($hasBuyPrice) {
                $buyPrice = $buyPrice - $n * $spread;
                $buyPrice = number_format($buyPrice, 2, '.', '');

                $hasBuyPrice = $this->orderService->buyPriceExists($buyPrice);
                if ($n > 15) {
                    $placeOrder  = false;
                    $hasBuyPrice = false;
                }
                $n++;
            }


            if ((is_null($lowestSellPrice) || $lowestSellPrice == 0 || $buyPrice < $lowestSellPrice) && $placeOrder) {
                $takeProfitAt = number_format($buyPrice + $profit, 2, '.', '');
                echo "Buy at: " . $buyPrice . "\n";
                echo "Buy size: " . $size . "\n";
                echo 'Take profit at: ' . $takeProfitAt . "\n";

                $order = $this->gdaxService->placeLimitBuyOrder($size, $buyPrice);

                if ($order->getId() && ($order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_PENDING || $order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)) {
                    $this->orderService->insertOrder('buy', $order->getId(), $size, $buyPrice, $this->getName(), $takeProfitAt);
                } else {
                    $this->orderService->insertOrder('buy', $order->getId(), $size, $buyPrice, $this->getName(), 0.0, 0, 0, $order->getMessage());
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

                            $this->orderService->insertOrder('sell', $sellOrder->getId(), $size, $sellPrice, $this->getName(), 0.0, 0, 0, 'open', $parent_id);

                            echo "Updating order status from pending to done: " . $row['order_id'] . "\n";
                            $this->orderService->updateOrderStatus($row['id'], $status);
                        } else {
                            $this->orderService->insertOrder('sell', $sellOrder->getId(), $size, $sellPrice, $this->getName(), 0.0, 0, 0, $sellOrder->getMessage(), $parent_id);
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
