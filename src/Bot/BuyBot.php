<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 09-01-18
 * Time: 10:41
 */

namespace App\Bot;

use App\Contracts\BotInterface;
use App\Traits\OHLC;
use App\Util\PositionConstants;

class BuyBot implements BotInterface
{

    use OHLC;

    protected $container;
    protected $config;
    protected $logger;
    protected $timestamp;
    
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
    protected $msg = [];

    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function setSettings(array $config = [])
    {
        $this->config = $config;
    }

    public function getMessage(): array
    {
        return $this->msg;
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

    protected function placeBuyOrder($size, $price): bool
    {
        $positionCreated = false;

        $order = $this->gdaxService->placeLimitBuyOrder($size, $price);

        if ($order->getId() && ($order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_PENDING || $order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)) {
            $this->orderService->buy($order->getId(), $size, $price);
            $positionCreated = true;
            $this->logger->info('Buy at '. $price. ' with size '.$size);
        } else {
            $reason = $order->getMessage() . $order->getRejectReason() . ' ';
            $this->orderService->insertOrder('buy', 'rejected', $size, $price, $reason);
            
            $this->logger->info('Buy rejected at '. $price. ' with size '.$size. ' reason '. $reason);
        }

        return $positionCreated;
    }

    protected function init()
    {
        $this->orderService    = $this->container->get('bot.service.order');
        $this->gdaxService     = $this->container->get('bot.service.gdax');
        $this->positionService = $this->container->get('bot.service.position');
        $this->logger          = $this->container->get('logger');
    }

    public function run()
    {
        $this->timestamp = \Carbon\Carbon::now('Europe/Amsterdam')->format('Y-m-d H:i:s');
        
        $this->init();
        $this->msg = [];
        
        $strategy = $this->getStrategy();
        $buyRule  = $this->getRule('buy');

        // Even when the limit is reached, i want to know the signal
        $signal = $strategy->getSignal();
        $this->msg    = array_merge($this->msg, $strategy->getMessage());


        $numOpenOrders        = (int) $this->orderService->getNumOpenBuyOrders() + (int) $this->positionService->getNumOpen();
        $numOrdersLeftToPlace = (int) $this->config['max_orders'] - $numOpenOrders;
        if (!$numOrdersLeftToPlace) {
            $numOrdersLeftToPlace = 0;
        }

        $botactive = ($this->config['botactive'] == 1 ? true : false);
        if (!$botactive) {
            $this->msg[] = $this->timestamp.' .... Bot is disabled';
        } else {

            $currentPrice = $this->gdaxService->getCurrentPrice();

            // Create safe limits
            $topLimit    = $this->config['top'];
            $bottomLimit = $this->config['bottom'];

            if (!$currentPrice || $currentPrice < 1 || $currentPrice > $topLimit || $currentPrice < $bottomLimit) {
                $this->msg[] = $this->timestamp . sprintf(" .... Treshold reached %s  [%s]  %s so no buying for now.", $bottomLimit, $currentPrice, $topLimit);
            } else {
                if ($signal == PositionConstants::BUY && $numOrdersLeftToPlace > 0) {

                    $size     = $this->config['size'];
                    $buyPrice = number_format($currentPrice - 0.01, 2, '.', '');
                    $this->msg[]    = $this->timestamp . " .... Place buyorder for size " . $size . ' and price ' . $buyPrice;
                    $this->placeBuyOrder($size, $buyPrice);
                } else {
                    if ($numOrdersLeftToPlace < 1) {
                        $this->msg[] = $this->timestamp . sprintf(" .... Num orders has been reached: Allowed: %d, placed %d", (int) $this->config['max_orders'], (int) $numOpenOrders);
                    }
                }
                $this->msg[] = $this->timestamp . " .... DONE ";
            }
        }
    }
}
