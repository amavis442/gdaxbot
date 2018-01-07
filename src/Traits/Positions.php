<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace App\Traits;

/**
 * Description of Positions
 *
 * @author patrickteunissen
 */
trait Positions
{
   protected function createPosition($size, $price): bool
    {
        $positionCreated = false;

        $order = $this->gdaxService->placeLimitBuyOrder($size, $price);
        if ($order->getId() && ($order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_PENDING || $order->getStatus() == \GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)) {
            $this->orderService->insertOrder('buy', $order->getId(), $size, $price);
            $positionCreated = true;
        } else {
            $reason = $order->getMessage() . $order->getRejectReason() . ' ';
            $this->orderService->insertOrder('buy', $order->getId(), $size, $price, $reason);
        }

        return $positionCreated;
    }

    /**
     * Checks the open buys and if they are filled then place a buy order for the same size but higher price
     */
    protected function updatePositions(float $currentPrice, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        $positions = $this->positionService->getOpen();

        if (is_array($positions)) {
            foreach ($positions as $position) {
                $price = $position['amount'];
                $size = $position['size'];
                $position_id = $position['id'];
                $order_id = $position['order_id']; // Buy order_id
                
                $sellMe = $this->stoplossRule->trailingStop($position_id, $currentPrice, $price, getenv('STOPLOSS'), $output);

                if ($sellMe) {
                    $sellPrice = number_format($currentPrice + 0.01, 2, '.', '');
                    $order = $this->gdaxService->placeLimitSellOrder($size, $sellPrice);
                    if ($order->getMessage()) {
                        $status = $order->getMessage();
                    } else {
                        $status = $order->getStatus();
                    }
                    
                    $buyOrder = $this->orderService->fetchOrderByOrderId($order_id);
                    $parent_id = $buyOrder->id;
                    
                    $this->orderService->insertOrder('sell', $order->getId(), $size, $price, $status, $parent_id, $position_id);
                }
            }
        }
    }
}
