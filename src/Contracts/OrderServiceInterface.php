<?php

namespace App\Contracts;

interface OrderServiceInterface {

    public function deleteOrder($id);

    public function updateOrder($id, $side);

    public function updateOrderStatus($id, $status);

    public function insertOrder($side, $order_id, $size, $amount, $status = 'pending', $parent_id = 0): int;

    public function garbageCollection();

    public function getPendingBuyOrders(): array;

    public function fetchAllOrders($status = 'pending'): array;

    public function fetchOrder($id): array;

    public function fetchOrderByOrderId($order_id): array;

    public function getNumOpenOrders(): int;
    
    /**
     * Get the lowest price of an open or pending sell
     */
    public function getLowestSellPrice();
    
    /**
     * Get orders which have a side. vb side = buy 
     * 
     * @param type $side
     * @param type $status
     */
    public function getOrdersBySide($side, $status = 'pending');

    /**
     * Get the open sell orders (status = open or pending)
     */
    public function getOrdersOpenSells(): array;

    /**
     * Get the buy orders with `status` open of `pending` 
     */
    public function getOrdersOpenBuys(): array;

    /**
     * Checks if a price is already in the active buy list
     * @param type $price
     */
    public function buyPriceExists($price): bool;

    /**
     * Some sells will be rejected coz of price going up while processing to make sure the buy gets sold 
     */
    public function fixRejectedSells();

    /**
     * Orders placed not by the bot
     * 
     * @param type $orders
     */
    public function fixUnknownOrdersFromGdax($orders);
}
