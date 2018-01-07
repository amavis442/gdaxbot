<?php
declare(strict_types = 1);
namespace App\Services;

use App\Contracts\GdaxServiceInterface;

/**
 * Description of GDaxService
 *
 * @author patrick
 */
class GDaxService implements GdaxServiceInterface
{

    protected $client;
    protected $account = [];
    protected $accountEUR;
    protected $accountLTC;
    protected $accountBTC;
    protected $cryptoCoin;

    /**
     *
     * @param \GDAX\Clients\AuthenticatedClient $client
     */
    public function __construct()
    {
        
    }

    /**
     * @param string $cryptoCoin
     */
    public function setCoin(string $cryptoCoin)
    {
        $this->cryptoCoin = $cryptoCoin;
    }

    /**
     * Connects to the gdax API
     *
     * @param type $sandbox
     */
    public function connect(bool $sandbox = false)
    {
        $this->client = new \GDAX\Clients\AuthenticatedClient(
            getenv('GDAX_API_KEY'), getenv('GDAX_API_SECRET'), getenv('GDAX_PASSWORD')
        );

        if ($sandbox) {
            $this->client->setBaseURL(\GDAX\Utilities\GDAXConstants::GDAX_API_SANDBOX_URL);
        }
    }

    /**
     * @return \GDAX\Types\Response\Market\ProductOrderBook
     */
    public function getOrderbook(): \GDAX\Types\Response\Market\ProductOrderBook
    {
        $product = (new \GDAX\Types\Request\Market\Product())->setProductId($this->getProductId())->setLevel(2);
        $productOrderBook = $this->client->getProductOrderBook($product);

        return $productOrderBook;
    }

    /**
     * @param string|null $date
     *
     * @return array
     */
    public function getTrades(string $date = null): array //\GDAX\Types\Response\Market\Trade[]
    {
        if (is_null($date)) {
            $date = date('Y') . '-01-01';
        }

        $publicClient = new \GDAX\Clients\PublicClient();

        $product = (new \GDAX\Types\Request\Market\Product())
            ->setProductId($this->getProductId())
            ->setStart(new \DateTime($date))
            ->setEnd(new \DateTime())
            ->setGranularity(1200);

        $productTrades = $publicClient->getTrades($product);

        return $productTrades;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
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

    /**
     * @param string $order_id
     *
     * @return \GDAX\Types\Response\Authenticated\Order
     */
    public function getOrder(string $order_id): \GDAX\Types\Response\Authenticated\Order
    {
        $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($order_id);
        $response = $this->client->getOrder($order);

        return $response;
    }

    /**
     * @return array
     */
    public function getOpenOrders(): array
    {

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
     * @return float
     */
    public function getCurrentPrice(): float
    {

        $product = (new \GDAX\Types\Request\Market\Product())->setProductId($this->getProductId());
        $productTicker = $this->client->getProductTicker($product);

        //Current asking price
        $startPrice = $productTicker->getPrice();

        return number_format($startPrice, 2, '.', '');
    }

    /**
     * @param string $order_id
     *
     * @return \GDAX\Types\Response\RawData
     */
    public function cancelOrder(string $order_id): \GDAX\Types\Response\RawData
    {
        $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($order_id);
        $response = $this->client->cancelOrder($order);

        return $response;
    }

    /**
     * Place a buy order
     *
     * @param float $size
     * @param float $price
     *
     * @return \GDAX\Types\Response\Authenticated\Order
     */
    public function placeLimitBuyOrder(float $size, float $price): \GDAX\Types\Response\Authenticated\Order
    {
        $order = (new \GDAX\Types\Request\Authenticated\Order())
            ->setType(\GDAX\Utilities\GDAXConstants::ORDER_TYPE_LIMIT)
            ->setProductId($this->getProductId())
            ->setSize($size)
            ->setSide(\GDAX\Utilities\GDAXConstants::ORDER_SIDE_BUY)
            ->setPrice($price)
            ->setTimeInForce(\GDAX\Utilities\GDAXConstants::TIME_IN_FORCE_GTT)
            ->setCancelAfter(\GDAX\Utilities\GDAXConstants::CANCEL_AFTER_MIN)
            ->setPostOnly(true);

        $response = $this->client->placeOrder($order);


        return $response;
    }

    /**
     *
     * @param float $size
     * @param float $price
     *
     * @return \GDAX\Types\Response\Authenticated\Order
     */
    public function placeLimitSellOrder(float $size, float $price): \GDAX\Types\Response\Authenticated\Order
    {
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
    public function getAccounts()
    {
        $accounts = $this->client->getAccounts();

        //Get the accounts
        foreach ($accounts as $account) {
            $currency = $account->getCurrency();
            if ($currency == 'LTC') {
                $this->account['LTC'] = $account;
            }

            if ($currency == 'EUR') {
                $this->account['EUR'] = $account;
            }

            if ($currency == 'BTC') {
                $this->account['BTC'] = $account;
            }
        }
    }

    public function getAccount(string $currency): \GDAX\Types\Response\Authenticated\Account
    {
        $this->getAccounts();

        return $this->account[$currency];
    }

    /**
     * @param string $coin
     *
     * @return mixed
     */
    public function getAccountReport(string $coin): array
    {

        $accounts = $this->client->getAccounts();

        $portfolio = 0.0;
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

    /**
     * @return array
     */
    public function getFills(): array
    {
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
