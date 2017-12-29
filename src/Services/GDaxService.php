<?php

namespace App\Services;

use App\Contracts\GdaxServiceInterface;

/**
 * Description of GDaxService
 *
 * @author patrick
 */
class GDaxService implements GdaxServiceInterface {

    protected $client;
    protected $accountEUR;
    protected $accountLTC;
    protected $accountBTC;
    protected $cryptoCoin;

    /**
     * 
     * @param \GDAX\Clients\AuthenticatedClient $client
     */
    public function __construct(\GDAX\Clients\AuthenticatedClient $client, string $cryptoCoin) {
        $this->client = $client;
        $this->cryptoCoin = $cryptoCoin;
    }

    /**
     * 
     * @return type
     */
    public function getProductId(): string {
        if ($this->cryptoCoin == 'LTC') {
            $product_id = \GDAX\Utilities\GDAXConstants::PRODUCT_ID_LTC_EUR;
        }
        if ($this->cryptoCoin == 'BTC') {
            $product_id = \GDAX\Utilities\GDAXConstants::PRODUCT_ID_BTC_EUR;
        }
        if ($this->cryptoCoin == 'ETH') {
            $product_id = \GDAX\Utilities\GDAXConstants::PRODUCT_ID_ETH_EUR;
        }

        return $product_id;
    }

    public function getOrder($order_id): \GDAX\Types\Response\Authenticated\Order {
        $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($order_id);
        $response = $this->client->getOrder($order);

        return $response;
    }

    public function getOpenOrders(): array {

        $listOrders = (new \GDAX\Types\Request\Authenticated\ListOrders())
                ->setStatus(\GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)
                ->setProductId($this->getProductId());

        $orders = $this->client->getOrders($listOrders);

        if (is_array($orders)) {
            return $orders;
        } else {
            return [];
        }
    }

    /**
     * What is the current asking price
     * 
     * @return type
     */
    public function getCurrentPrice() {

        $product = (new \GDAX\Types\Request\Market\Product())->setProductId($this->getProductId());
        $productTicker = $this->client->getProductTicker($product);

        //Current asking price
        $startPrice = $productTicker->getPrice();

        return $startPrice;
    }

    public function cancelOrder($order_id): \GDAX\Types\Response\RawData {
        $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($order_id);
        $response = $this->client->cancelOrder($order);

        return $response;
    }

    /**
     * Place a buy order 
     * 
     * @param type $price
     * @return boolean
     */
    public function placeLimitBuyOrder($size, $price): \GDAX\Types\Response\Authenticated\Order {
        $order = (new \GDAX\Types\Request\Authenticated\Order())
                ->setType(\GDAX\Utilities\GDAXConstants::ORDER_TYPE_LIMIT)
                ->setProductId($this->getProductId())
                ->setSize($size)
                ->setSide(\GDAX\Utilities\GDAXConstants::ORDER_SIDE_BUY)
                ->setPrice($price)
                ->setPostOnly(true);

        $response = $this->client->placeOrder($order);


        return $response;
        /* if (isset($response) && $response->getId() && $response->getMessage() != 'rejected') {
          return $response->getId();
          } else {
          echo "Order not placed because : " . $response->getMessage() . "\n";
          return false;
          } */
    }

    /**
     * 
     * @param string $size
     * @param decimal $price
     * @return boolean
     */
    public function placeLimitSellOrder($size, $price): \GDAX\Types\Response\Authenticated\Order {
        $order = (new \GDAX\Types\Request\Authenticated\Order())
                ->setType(\GDAX\Utilities\GDAXConstants::ORDER_TYPE_LIMIT)
                ->setProductId($this->getProductId())
                ->setSize($size)
                ->setSide(\GDAX\Utilities\GDAXConstants::ORDER_SIDE_SELL)
                ->setPrice($price)
                ->setPostOnly(true);

        $response = $this->client->placeOrder($order);
        /* if (isset($response) && $response->getId() && $response->getMessage() != 'rejected') {
          return $response->getId();
          } else {
          echo "Order not placed because : " . $response->getMessage() . "\n";
          return false;
          } */
    }

    /**
     * Get acount data like balance (can be handy to check if there is enough funds left)
     */
    public function getAccounts() {
        $accounts = $this->client->getAccounts();

        //Get the accounts
        foreach ($accounts as $account) {
            $currency = $account->getCurrency();
            if ($currency == 'LTC') {
                $this->accountLTC = (new \GDAX\Types\Request\Authenticated\Account())->setId($account->getId());
            }

            if ($currency == 'EUR') {
                $this->accountEUR = (new \GDAX\Types\Request\Authenticated\Account())->setId($account->getId());
            }

            if ($currency == 'BTC') {
                $this->accountBTC = (new \GDAX\Types\Request\Authenticated\Account())->setId($account->getId());
            }
        }
    }

    public function getFills(): array {
        $fill = (new \GDAX\Types\Request\Authenticated\Fill())
                ->setProductId($this->getProductId());

        $fillData = $this->client->getFills($fill); // GDAX\Types\Response\Authenticated\Fill[]
        if (is_array($fillData)) {
            return $fillData;
        } else {
            return [];
        }
    }

}
