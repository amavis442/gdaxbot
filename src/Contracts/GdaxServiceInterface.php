<?php

namespace App\Contracts;

interface GdaxServiceInterface {

    /**
     * Get an order by order_id which has a format like aaaaaa-aaaa-aaaa-aaaaa
     * 
     * @param type $order_id
     */
    public function getOrder($order_id): \GDAX\Types\Response\Authenticated\Order;

    /**
     * Get the productid vb BTC-EUR
     */
    public function getProductId(): string;

    /**
     * Get the orders with status open or pending
     */
    public function getOpenOrders(): array;

    /**
     * Get the last ask price
     */
    public function getCurrentPrice();

    /**
     * Cancel a open/pending order
     * 
     * @param type $order_id
     */
    public function cancelOrder($order_id): \GDAX\Types\Response\RawData;

    /**
     * Place a buy order of a certain size and the limit price
     * 
     * @param type $size
     * @param type $price
     */
    public function placeLimitBuyOrder($size, $price) : \GDAX\Types\Response\Authenticated\Order;

    /**
     * Place a sell order of a certain size and the limit price
     * 
     * @param type $size
     * @param type $price
     */
    public function placeLimitSellOrder($size, $price): \GDAX\Types\Response\Authenticated\Order;

    /**
     * Get the accounts (balance etc)
     */
    public function getAccounts();
    
    /**
     * Get the fills for a certain product_id (vb. BTC-EUR)
     */
    public function getFills(): array;
}
