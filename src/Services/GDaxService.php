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
    public function __construct() {
        
    }

    public function setCoin(string $cryptoCoin) {
        $this->cryptoCoin = $cryptoCoin;
    }
    
    /**
     * Connects to the gdax API
     * 
     * @param type $sandbox
     */
    public function connect($sandbox = false) {
        $this->client = new \GDAX\Clients\AuthenticatedClient(
                getenv('GDAX_API_KEY'), getenv('GDAX_API_SECRET'), getenv('GDAX_PASSWORD')
        );
        
        if ($sandbox) {
            $this->client->setBaseURL(\GDAX\Utilities\GDAXConstants::GDAX_API_SANDBOX_URL);
        }
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

    public function getOrder(string $order_id): \GDAX\Types\Response\Authenticated\Order {
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

    public function cancelOrder(string $order_id): \GDAX\Types\Response\RawData {
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

        return $response;
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
    
    public function getAccountReport(string $coin) {

        $accounts = $this->client->getAccounts();

        $portfolio = 0;
        /** @var  \GDAX\Types\Response\Authenticated\Account $account */
        foreach ($accounts as $account) {
            $currency = $account->getCurrency();
            $balance = $account->getBalance();

            if ($currency != 'EUR') {
                $product = (new \GDAX\Types\Request\Market\Product())->setProductId($currency . '-EUR');
                $productTicker = $this->client->getProductTicker($product);
                $koers = number_format($productTicker->getPrice(), 3, '.', '');
            } else {
                $koers = 0.0;
            }
            $waarde = 0.0;
            if ($currency == 'EUR') {
                $balance = number_format($balance, 4, '.', '');
                $waarde = $balance;
            } else {
                $waarde = number_format($balance * $koers, 4, '.', '');
            }
            
            $balances[$currency] = ['balance' => $balance, 'koers' => $koers, 'waarde' => $waarde];
        }
        
        return $balances[$coin];
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
