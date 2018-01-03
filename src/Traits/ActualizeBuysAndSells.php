<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 02-01-18
 * Time: 15:45
 */

namespace App\Traits;


/**
 * Trait ActualizeBuysAndSells
 *
 * @package App\Traits
 */
trait ActualizeBuysAndSells
{
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
                } else {
                    $this->orderService->updateOrderStatus($row['id'], $order->getMessage());
                }
            }
        }
    }
}