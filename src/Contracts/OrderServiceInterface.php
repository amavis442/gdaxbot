<?php

namespace App\Contracts;

/**
 * Interface OrderServiceInterface
 *
 * @package App\Contracts
 */
interface OrderServiceInterface {

    /**
     * Delete an order record
     *
     * @param $id
     *
     * @return mixed
     */
    public function deleteOrder(int $id);

    /**
     * Update an order record
     *
     * @param $id
     * @param $side
     *
     * @return mixed
     */
    public function updateOrder(int $id, string $side);

    /**
     * @param $id
     * @param $status
     *
     * @return mixed
     */
    public function updateOrderStatus(int $id, string $status);

    /**
     * @param string $side
     * @param string $order_id
     * @param float  $size
     * @param float  $amount
     * @param string $strategy
     * @param float  $take_profit
     * @param int    $signalpos
     * @param int    $signalneg
     * @param string $status
     * @param int    $parent_id
     *
     * @return int
     */
    public function insertOrder(string $side, string $order_id, float $size, float $amount, string $strategy = 'TrendsLines', float $take_profit = 13000.0, int $signalpos = 0, int $signalneg = 0, string $status = 'pending', int $parent_id = 0): int;

    /**
     * @return mixed
     */
    public function garbageCollection();

    /**
     * @return array
     */
    public function getPendingBuyOrders(): array;

    /**
     * @param string $status
     *
     * @return array
     */
    public function fetchAllOrders(string $status = 'pending'): array;

    /**
     * @param int $id
     *
     * @return \stdClass
     */
    public function fetchOrder(int $id): \stdClass;

    /**
     * @param string $order_id
     *
     * @return \stdClass
     */
    public function fetchOrderByOrderId(string $order_id): \stdClass;

    /**
     * @return int
     */
    public function getNumOpenOrders(): int;

    /**
     * @param string|null $date
     *
     * @return array
     */
    public function getProfits(string $date = null) : array;

    /**
     * Get the lowest price of an open or pending sell
     */
    public function getLowestSellPrice();
    
    /**
     * Get orders which have a side. vb side = buy 
     * 
     * @param string $side
     * @param string $status
     *
     * @return array
     */
    public function getOrdersBySide(string $side, string $status = 'pending'): array;

    /**
     * Get the open sell orders (status = open or pending)
     */
    public function getOpenSellOrders(): array;

    /**
     * Get the buy orders with `status` open of `pending` 
     */
    public function getOpenBuyOrders(): array;

    /**
     * Checks if a price is already in the active buy list
     * @param type $price
     */
    public function buyPriceExists(float $price): bool;

    /**
     * Some sells will be rejected coz of price going up while processing to make sure the buy gets sold 
     */
    public function fixRejectedSells();

    /**
     * @param array|null $orders
     *
     * @return mixed
     */
    public function fixUnknownOrdersFromGdax(array $orders = null);
}
