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
    public function actualizeBuys()
    {
        $rows = $this->orderService->getOpenBuyOrders();

        if (count($rows)) {
            foreach ($rows as $row) {
                $order = $this->gdaxService->getOrder($row['order_id']);
                $position_id = 0;
                $status = $order->getStatus();

                if ($status) {
                    if ($status == 'done') {
                        $position_id = $this->positionService->open($order->getId(), $order->getSize(), $order->getPrice());
                    }
                    $this->orderService->updateOrderStatus($row['id'], $order->getStatus(), $position_id);
                } else {
                    $this->orderService->updateOrderStatus($row['id'], $order->getMessage(), $position_id);
                }
            }
        }
    }

    /**
     * Update the open Sells
     */
    public function actualizeSells()
    {
        $rows = $this->orderService->getOpenSellOrders();

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $order = $this->gdaxService->getOrder($row['order_id']);


                $status = $order->getStatus();
                $parent_id = $row['parent_id'];

                if ($status) {
                    $this->orderService->updateOrderStatus($row['id'], $order->getStatus());

                    if ($status == 'done') {
                        $position_id = $row['position_id'];
                        $buyorder = $this->orderService->fetchOrderByParentId($parent_id);
                        $this->positionService->close($buyorder->position_id);
                    }
                } else {
                    $this->orderService->updateOrderStatus($row['id'], $order->getMessage());
                }
            }
        }
    }
}
