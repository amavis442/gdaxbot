<?php

namespace App\Contracts;

/**
 * Interface GdaxServiceInterface
 *
 * @package App\Contracts
 */
interface GdaxServiceInterface
{

    /**
     * Which crypto coin (BTC,LTC,ETH)
     *
     * @param string $cryptoCoin
     */
    public function setCoin(string $cryptoCoin);

    /**
     * Connect to the gdax API
     *
     * @param bool $sandbox
     */
    public function connect(bool $sandbox = false);

    public function getClient(): \GDAX\Clients\AuthenticatedClient;

    /**
     * Returns orderbook level 2
     *
     * @return \GDAX\Types\Response\Market\ProductOrderBook
     */
    public function getOrderbook(): \GDAX\Types\Response\Market\ProductOrderBook;

    /**
     * Get trades from starting from date
     *
     * @param string|null $date
     *
     * @return array
     */
    public function getTrades(string $date = null): array;

    /**
     * Get an order by order_id which has a format like aaaaaa-aaaa-aaaa-aaaaa
     *
     * @param string $order_id
     *
     * @return \GDAX\Types\Response\Authenticated\Order
     */
    public function getOrder(string $order_id): \GDAX\Types\Response\Authenticated\Order;

    /**
     * Get the productid vb BTC-EUR
     *
     * @return string
     */
    public function getProductId(): string;

    /**
     * Get the orders with status open or pending
     *
     * @return array
     */
    public function getOpenOrders(): array;

    /**
     * Get the last ask price
     *
     * @return float
     */
    public function getCurrentPrice() : float;

    /**
     * Cancel a open/pending order
     *
     * @param string $order_id
     *
     * @return \GDAX\Types\Response\RawData
     */
    public function cancelOrder(string $order_id): \GDAX\Types\Response\RawData;

    /**
     * Place a buy order of a certain size and the limit price
     *
     * @param float $size
     * @param float $price
     *
     * @return \GDAX\Types\Response\Authenticated\Order
     */
    public function placeLimitBuyOrder(float $size, float $price): \GDAX\Types\Response\Authenticated\Order;

    /**
     * Place a sell order of a certain size and the limit price
     *
     * @param float $size
     * @param float $price
     *
     * @return \GDAX\Types\Response\Authenticated\Order
     */
    public function placeLimitSellOrder(float $size, float $price): \GDAX\Types\Response\Authenticated\Order;

    /**
     * Get the accounts (balance etc)
     */
    public function getAccounts();
    
    public function getAccount(string $currency): \GDAX\Types\Response\Authenticated\Account;
    

    /**
     * Get the fills for a certain product_id (vb. BTC-EUR)
     *
     * @return array
     */
    public function getFills(): array;

    /**
     * Report balance, current price and the value in euro's
     *
     * @param string $product
     *
     * @return array
     */
    public function getAccountReport(string $product): array;
}
