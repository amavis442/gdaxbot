<?php

namespace App\Bot;

use Carbon\Carbon;

/**
 * Description of Gdaxbot
 *
 * @author patrick
 */
class Gdaxbot {

    protected $endpoint;
    protected $spread;
    protected $order_size;
    protected $max_orders_per_run;
    protected $waitingtime;
    protected $lifetime;
    protected $api_key;
    protected $api_secret;
    protected $api_password;
    protected $db;
    protected $client;
    protected $accountEUR;
    protected $accountLTC;
    protected $pendingBuyPrices;
    protected $buyingTreshold;
    protected $sellingTreshold;

// Get the number of open LTC orders 
// Calc allowed number of order = max_orders - open orders.
// Get the max bid price and substract 10
    public function __construct() {
        $this->endpoint = getenv('GDAX_ENDPOINT');
        $this->spread = getenv('spread');
        $this->order_size = getenv('order_size');
        $this->max_orders = getenv('max_orders');
        $this->max_orders_per_run = getenv('max_orders_per_run');
        $this->waitingtime = getenv('waitingtime');
        $this->lifetime = getenv('lifetime');
        $this->buyingTreshold = getenv('BUYINGTRHESHOLD');
        $this->sellingTreshold = getenv('SELLINGTRESHOLD');


        $this->db = new \PDO('sqlite:orders.sqlite');

        $this->client = new \GDAX\Clients\AuthenticatedClient(
                getenv('GDAX_API_KEY'), getenv('GDAX_API_SECRET'), getenv('GDAX_PASSWORD')
        );
    }

    public function createDatabase() {
        $this->db->exec("CREATE TABLE orders (id INTEGER PRIMARY KEY, side TEXT, amount TEXT,order_id TEXT, created_at TEXT)");
    }

    public function purgeDatabase() {
        $this->db->exec('delete from orders');
    }

    public function listRowsFromDatabase() {
        $currentPendingOrders = $this->db->query('SELECT * FROM orders');
        foreach ($currentPendingOrders as $row) {
            var_dump($row);
        }
    }

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
        }
    }

    public function getCurrentPrice() {
        $product = (new \GDAX\Types\Request\Market\Product())->setProductId(\GDAX\Utilities\GDAXConstants::PRODUCT_ID_LTC_EUR);
        $productTicker = $this->client->getProductTicker($product);

        //Current asking price
        $startPrice = $productTicker->getPrice();

        return $startPrice;
    }

    public function cancelOrPurgeOrders() {
        $currentPendingOrders = $this->db->query('SELECT * FROM orders');

        foreach ($currentPendingOrders as $orderSqlite) {
            if (empty($orderSqlite['order_id'])) {
                $this->db->exec('delete from orders where id =' . $orderSqlite['id']);
                continue;
            }

            // Get the status of the buy order. You can only sell what you got.
            $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($orderSqlite['order_id']);
            /** \GDAX\Types\Response\Authenticated\Order $orderData */
            $orderData = $this->client->getOrder($order);
            $status = $orderData->getStatus();

            // Check for old orders and if so cancel them to start over
            if ($status != 'done') {
                $diffInSecs = Carbon::createFromFormat('Y-m-d H:i:s', $orderSqlite['created_at'])->diffInSeconds(Carbon::now());

                if (Carbon::createFromFormat('Y-m-d H:i:s', $orderSqlite['created_at'])->diffInSeconds(Carbon::now()) > $this->lifetime) {

                    $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($orderSqlite['order_id']);
                    $response = $this->client->cancelOrder($order);

                    if (isset($response)) {
                        echo "Order " . $orderSqlite['order_id'] . " is older then " . $this->lifetime . " seconds (" . $diffInSecs . ") and will be deleted\n";
                        $this->db->exec('delete from orders where id =' . $orderSqlite['id']);
                    } else {
                        echo "Could not cancel order " . $orderSqlite['order_id'] . " for " . $orderSqlite['amount'] . "\n";
                    }
                }
            }

            if (is_null($status)) {
                $this->db->exec('delete from orders where id =' . $orderSqlite['id']);
            }
        }
    }

    public function sell() {
        $startPrice = $this->getCurrentPrice();

        $currentPendingOrders = $this->db->query('SELECT * FROM orders');

        $n = 1;
        foreach ($currentPendingOrders as $orderSqlite) {
            // Get the status of the buy order. You can only sell what you got.
            $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($orderSqlite['order_id']);
            $orderData = $this->client->getOrder($order);

            /** \GDAX\Types\Response\Authenticated\Order $orderData */
            $status = $orderData->getStatus();

            if ($status == 'done') {
                $buyprice = $orderSqlite['amount'];
                $sellPrice = $buyprice + 0.01 + $spread;
                if ($startPrice > $sellPrice) {
                    $sellPrice = $startPrice + ($n * 0.05);
                }

                echo 'Sell ' . $order_size . ' for ' . $sellPrice . "\n";

                if ($startPrice < $this->sellingTreshold) {
                    printf("Reached sell treshold %s  [%s] so no selling for now\n", $this->sellingTreshold, $startPrice);
                    continue;
                }
        
                $order = (new \GDAX\Types\Request\Authenticated\Order())
                        ->setType(\GDAX\Utilities\GDAXConstants::ORDER_TYPE_LIMIT)
                        ->setProductId(\GDAX\Utilities\GDAXConstants::PRODUCT_ID_LTC_EUR)
                        ->setSize($order_size)
                        ->setSide(\GDAX\Utilities\GDAXConstants::ORDER_SIDE_SELL)
                        ->setPrice($sellPrice)
                        ->setPostOnly(true);

                $response = $this->client->placeOrder($order);

                if (isset($response) && !is_null($response) && $response->getStatus() == 'pending') {
                    echo "Removing buy from records: " . $orderSqlite['order_id'] . "\n";
                    $this->db->exec('delete from orders where id =' . $orderSqlite['id']);
                }
            } else {
                echo "Order not done " . $orderSqlite['order_id'] . "\n";
            }
        }
    }

    public function getOpenOrders() {
        $lowestSellPrice = 1000.0;

        $listOrders = (new \GDAX\Types\Request\Authenticated\ListOrders())
                ->setStatus(\GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)
                ->setProductId(\GDAX\Utilities\GDAXConstants::PRODUCT_ID_LTC_EUR);

        $orders = $this->client->getOrders($listOrders);
        foreach ($orders as $order) {
            $price = $order->getPrice();
            if ($order->getSide() == 'sell') {
                if ($price < $lowestSellPrice) {
                    $lowestSellPrice = $price;
                }
            } else {
                $this->pendingBuyPrices[] = $price;
            }
        }

        $restOrders = $this->max_orders - count($orders);
        echo "Maximum number of orders left to place: " . $restOrders . " of " . $this->max_orders . "\n";
        echo "Lowest sellprice " . $lowestSellPrice . "\n";

        return [$restOrders, $lowestSellPrice];
    }

    public function buy() {
        list($restOrders, $lowestSellPrice) = $this->getOpenOrders();

        if ($this->max_orders_per_run > $restOrders) {
            $ordersToPlace = $this->max_orders_per_run;
        } else {
            $ordersToPlace = $restOrders;
        }

        $startPrice = $this->getCurrentPrice();

        if ($startPrice > $this->buyingTreshold || $startPrice < $this->sellingTreshold) {
            printf("Treshold reached %s  [%s]  %s so no buying for now\n", $this->sellingTreshold, $startPrice, $this->buyingTreshold);
            return;
        }

        for ($i = 1; $i <= $restOrders; $i++) {
            // for buys
            $buyPrice = $startPrice - 0.01 - $i * $this->spread;


            if ($buyPrice < $lowestSellPrice) {
                echo 'Buy ' . $this->order_size . ' for ' . $buyPrice . "\n";

                $order = (new \GDAX\Types\Request\Authenticated\Order())
                        ->setType(\GDAX\Utilities\GDAXConstants::ORDER_TYPE_LIMIT)
                        ->setProductId(\GDAX\Utilities\GDAXConstants::PRODUCT_ID_LTC_EUR)
                        ->setSize($this->order_size)
                        ->setSide(\GDAX\Utilities\GDAXConstants::ORDER_SIDE_BUY)
                        ->setPrice($buyPrice)
                        ->setPostOnly(true);

                $response = $this->client->placeOrder($order);

                if (isset($response) && !is_null($response) && $response->getStatus() == 'pending') {
                    $order_id = $response->getId();
                    $this->db->exec("INSERT INTO orders (side, amount, order_id, created_at) VALUES ('buy', '" . $buyPrice . "','" . $order_id . "','" . date('Y-m-d H:i:s') . "');");
                } else {
                    echo "Order not placed for " . $buyPrice . "\n";
                }
            } else {
                echo "We have open sells that will cross the buys and that is not allowed:" . $buyPrice . "\n";
            }
        }
    }

    public function run() {
        $this->cancelOrPurgeOrders();
        $this->sell();
        $this->buy();

        echo "\nDONE\n";
    }

}
