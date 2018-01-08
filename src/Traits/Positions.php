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
                
                $placeOrder = true;
                if ($sellMe) {
                    $buyOrder = $this->orderService->fetchOrderByOrderId($order_id);
                    $parent_id = $buyOrder->id;
                    // Check if there are sell order for this position and cancel them.
                    $existingSellOrder = $this->orderService->fetchOrderByParentId($parent_id);
                    if ($existingSellOrder) {
                        // Give the order 1 minute to complete
                        $created_at = $existingSellOrder->created_at;
                        if (\Carbon\Carbon::parse('Y-m-d H:i:s', $created_at)->addMinute(1)->format('YmdHis') < \Carbon\Carbon::now()->format('YmdHis')) {
                            $this->gdaxService->cancelOrder($existingSellOrder->order_id);
                            $this->orderService->updateOrderStatus($existingSellOrder->id,'cancelled');
                        } else {
                            $placeOrder = false;
                        }
                    }
                    
                    if ($placeOrder) {
                        $sellPrice = number_format($currentPrice + 0.01, 2, '.', '');
                        $order = $this->gdaxService->placeLimitSellOrder($size, $sellPrice);
                        if ($order->getMessage()) {
                            $status = $order->getMessage();
                        } else {
                            $status = $order->getStatus();
                        }
                        $this->orderService->insertOrder('sell', $order->getId(), $size, $price, $status, $parent_id, $position_id);
                    }
                } else {
                    // No more need to sell
                    $buyOrder = $this->orderService->fetchOrderByOrderId($order_id);
                    $parent_id = $buyOrder->id;
                    $existingSellOrder = $this->orderService->fetchOrderByParentId($parent_id);
                    if ($existingSellOrder) {
                        // Give the order 1 minute to complete
                        $this->gdaxService->cancelOrder($existingSellOrder->order_id);
                        $this->orderService->updateOrderStatus($existingSellOrder->id,'cancelled');
                    }
                }
            }
        }
    }
}
