<?php

namespace App\Bot;

use Carbon\Carbon;

/**
 * Description of Gdaxbot
 *
 * @author patrick
 */
class Gdaxbot {

    protected $conn;
    protected $endpoint;
    protected $spread;
    protected $sellspread;
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
    protected $accountBTC;
    protected $pendingBuyPrices;
    protected $bottomBuyingTreshold;
    protected $topBuyingTreshold;
    protected $cryptoCoin='LTC';

// Get the number of open LTC orders 
// Calc allowed number of order = max_orders - open orders.
// Get the max bid price and substract 10
    public function __construct($conn) {
        $this->conn = $conn;

        $sql = "SELECT * FROM settings order by id desc limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $settings = $stmt->fetch();

        
        $this->cryptoCoin = getenv('CRYPTOCOIN');
        $this->endpoint = getenv('GDAX_ENDPOINT');
        $this->max_orders_per_run = getenv('MAX_ORDERS_PER_RUN');
        $this->waitingtime = getenv('WAITINGTIME');

        $this->spread = $settings['spread'];
        $this->sellspread = $settings['sellspread'];
        $this->order_size = $settings['size'];
        $this->max_orders = $settings['max_orders'];

        $this->lifetime = $settings['lifetime'];

        $this->bottomBuyingTreshold = $settings['bottom'];
        $this->topBuyingTreshold = $settings['top'];

        $this->client = new \GDAX\Clients\AuthenticatedClient(
                getenv('GDAX_API_KEY'), getenv('GDAX_API_SECRET'), getenv('GDAX_PASSWORD')
        );
    }

    public function createDatabase() {

        $sql = "CREATE TABLE orders (id INTEGER PRIMARY KEY AUTO_INCREMENT, parent_id integer, side varchar(10), size varchar(20), amount decimal(15,9),status varchar(10), order_id varchar(40), created_at datetime, updated_at timestamp);";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $sql = "CREATE TABLE settings (id INTEGER PRIMARY KEY AUTO_INCREMENT, spread decimal(8,2),sellspread decimal(8,2), max_orders int, bottom decimal(10,2),top decimal(10,2) ,size varchar(10),lifetime int, created_at datetime, updated_at timestamp);";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $sql = "INSERT INTO settings SET spread= :spread, max_orders=:maxorders, top=:top, bottom = :bottom, size=:size,lifetime =:lifetime, created_at = :createdat;";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('spread', 0.01);
        $stmt->bindValue('maxorders', 8);
        $stmt->bindValue('top', 220.0);
        $stmt->bindValue('bottom', 218.0);
        $stmt->bindValue('size', 0.02);
        $stmt->bindValue('lifetime', 90);
        $stmt->bindValue('createdat', date('Y-m-d H:i:s'));

        $stmt->execute();
    }

    public function purgeDatabase() {
        $sql = 'delete from orders';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
    }

    public function deleteOrder($id) {
        $sql = 'delete from orders WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('id', $id);
        $stmt->execute();
    }

    public function updateOrder($id, $side) {
        $sql = 'update orders SET side = :side WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('id', $id);
        $stmt->bindValue('side', $side);
        $stmt->execute();
    }

    public function updateOrderStatus($id, $status) {
        $sql = 'update orders SET status = :status WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('id', $id);
        $stmt->bindValue('status', $status);
        $stmt->execute();
    }

    /**
     * Insert an order into the database 
     * 
     * @param string $side
     * @param string $order_id
     * @param string $size
     * @param string $amount
     * @param string $status
     * @param int $parent_id
     * @return int
     */
    public function insertOrder($side, $order_id, $size, $amount, $status = 'pending', $parent_id = 0) {
        $sql = 'insert into orders SET side = :side, order_id = :orderid, size = :size, amount = :amount,status = :status, parent_id = :parentid, created_at = :createdat';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('side', $side);
        $stmt->bindValue('orderid', $order_id);
        $stmt->bindValue('size', $size);
        $stmt->bindValue('amount', $amount);
        $stmt->bindValue('status', $status);
        $stmt->bindValue('parentid', $parent_id);

        $stmt->bindValue('createdat', date('Y-m-d H:i:s'));

        $stmt->execute();

        $lastId = $this->conn->query('SELECT max(id) as insertid FROM orders')->fetch();

        return (int) $lastId['insertid'];
    }

    /**
     * Get rid of the failures.
     */
    public function deleteOrdersWithoutOrderId() {
        $sql = "update orders SET status = :status WHERE order_id = '' AND status <> :status";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('status', 'deleted');
        $stmt->execute();
    }

    /**
     * Get the open buy orders
     * 
     * @return array
     */
    public function getPendingBuyOrders() {
        $sql = "SELECT * FROM orders WHERE side='buy' AND (status = 'pending' or status='open')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Fetch all orders that have given status
     * 
     * @param string $status
     * @return array
     */
    public function fetchAllOrders($status = 'pending') {
        $sql = 'SELECT * FROM orders WHERE status = :status';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('status', $status);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Fetch inidividual order 
     * 
     * @param int $id
     * @return array
     */
    public function fetchOrder($id) {
        $sql = 'SELECT * FROM orders WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Fetch order by order id from coinbase (has the form of aaaaa-aaaa-aaaa-aaaaa)
     * 
     * @param type $order_id
     * @return type
     */
    public function fetchOrderByOrderId($order_id) {
        $sql = 'SELECT * FROM orders WHERE order_id = :orderid';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('orderid', $order_id);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * 
     * @param string $side
     * @param string $status
     * @return array
     */
    public function getOrdersBySide($side, $status = 'pending') {
        $sql = "SELECT * FROM orders WHERE side = :side AND status = :status";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('side', $side);
        $stmt->bindValue('status', $status);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get the open sell orders
     * 
     * @return array
     */
    public function getOrdersOpenSells() {
        $sql = "SELECT * FROM orders WHERE side = :side AND (status = 'pending' OR status = 'open')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('side', 'sell');
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get the open buy orders
     * 
     * @return array
     */
    public function getOrdersOpenBuys() {
        $sql = "SELECT * FROM orders WHERE side = :side AND (status = 'pending' OR status = 'open')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('side', 'buy');
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Check of we already have a open buy order with that price
     * 
     * @param type $price
     * @return boolean
     */
    public function buyPriceExists($price) {
        $sql = "SELECT * FROM orders WHERE side = :side AND (status = 'pending' OR status = 'open') AND amount = :amount";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('side', 'buy');
        $stmt->bindValue('amount', $price);
        $stmt->execute();

        $result = $stmt->fetch();

        if ($result && $result['amount']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 
     */
    public function listRowsFromDatabase() {
        $currentPendingOrders = $this->fetchAllOrders();
        foreach ($currentPendingOrders as $row) {
            printf("%s| %s| %s| %s\n", $row['created_at'], $row['side'], $row['amount'], $row['order_id']);
        }
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

    protected function getProductId()
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

    /**
     * Check if we have added orders manually and add them to the database.
     */
    public function actualize() {


        $listOrders = (new \GDAX\Types\Request\Authenticated\ListOrders())
                ->setStatus(\GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)
                ->setProductId($this->getProductId());

        $orders = $this->client->getOrders($listOrders);
        if (is_array($orders)) {
            foreach ($orders as $order) {
                $order_id = $order->getId();
                $row = $this->fetchOrderByOrderId($order_id);
                if (!$row) {
                    $this->insertOrder($order->getSide(), $order->getId(), $order->getSize(), $order->getPrice());
                } else {
                    if ($row['status'] != 'done') {
                        $this->updateOrderStatus($row['id'], $order->getStatus());
                    }
                }
            }
        }
    }

    /**
     * Update the open buys
     */
    public function actualizeBuys() {
        $rows = $this->getOrdersOpenBuys();

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($row['order_id']);
                $orderData = $this->client->getOrder($order);

                if ($orderData->getStatus()) {
                    $this->updateOrderStatus($row['id'], $orderData->getStatus());

                    //echo "Actualize sells with order id: " . $order->getId() . "\n";
                }

                if ($orderData->getStatus()) {
                    $this->updateOrderStatus($row['id'], $orderData->getStatus());
                } else {
                    $this->updateOrderStatus($row['id'], $orderData->getMessage());
                }
            }
        }
    }

    /**
     * Update the open Sells
     */
    public function actualizeSells() {
        $rows = $this->getOrdersOpenSells();

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($row['order_id']);
                $orderData = $this->client->getOrder($order);

                if ($orderData->getStatus()) {
                    $this->updateOrderStatus($row['id'], $orderData->getStatus());

                    //echo "Actualize sells with order id: " . $order->getId() . "\n";
                }
                if ($orderData->getMessage() == 'NotFound') {
                    $this->updateOrderStatus($row['id'], $orderData->getStatus());
                }
            }
        }
    }

    /**
     * Cancel pending/open buy orders that have not filled yet in x seconds (90 seconds for now)
     */
    public function cancelOldBuyOrders() {
        $currentPendingOrders = $this->getPendingBuyOrders();

        if (is_array($currentPendingOrders)) {
            foreach ($currentPendingOrders as $row) {

                // Get the status of the buy order. You can only sell what you got.
                $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($row['order_id']);

                /** \GDAX\Types\Response\Authenticated\Order $orderData */
                $orderData = $this->client->getOrder($order);

                if ($orderData instanceof \GDAX\Types\Response\Authenticated\Order) {
                    $status = $orderData->getStatus();
                } else {
                    $status = null;
                }

                // Check for old orders and if so cancel them to start over
                if ($status == 'pending' || $status == 'open') {
                    $diffInSecs = Carbon::createFromFormat('Y-m-d H:i:s', $row['created_at'])->diffInSeconds(Carbon::now());

                    if (Carbon::createFromFormat('Y-m-d H:i:s', $row['created_at'])->diffInSeconds(Carbon::now()) > $this->lifetime) {

                        $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($row['order_id']);
                        $response = $this->client->cancelOrder($order);

                        echo $response->getMessage() . "\n";

                        if (isset($response)) {
                            echo "Order " . $row['order_id'] . " is older then " . $this->lifetime . " seconds (" . $diffInSecs . ") and will be deleted\n";
                            $this->updateOrderStatus($row['id'], 'deleted');
                        } else {
                            echo "Could not cancel order " . $row['order_id'] . " for " . $row['amount'] . "\n";
                        }
                    }
                }

                if (is_null($status)) {
                    echo "Order not found with order id: " . $row['order_id'] . "\n";
                    $this->updateOrderStatus($row['id'], $orderData->getMessage());
                }
            }
        }
    }

    /**
     * 
     * @param string $size
     * @param decimal $price
     * @return boolean
     */
    protected function placeSellOrder($size, $price) {
        $order = (new \GDAX\Types\Request\Authenticated\Order())
                ->setType(\GDAX\Utilities\GDAXConstants::ORDER_TYPE_LIMIT)
                ->setProductId($this->getProductId())
                ->setSize($size)
                ->setSide(\GDAX\Utilities\GDAXConstants::ORDER_SIDE_SELL)
                ->setPrice($price)
                ->setPostOnly(true);

        $response = $this->client->placeOrder($order);
        if (isset($response) && $response->getId() && $response->getMessage() != 'rejected') {
            return $response->getId();
        } else {
            echo "Order not placed because : " . $response->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Checks the open buys and if they are filled then place a buy order for the same size but higher price
     */
    public function sell() {
        $startPrice = $this->getCurrentPrice();
        $currentPendingOrders = $this->getOrdersOpenBuys();

        $n = 1;
        if (is_array($currentPendingOrders)) {
            foreach ($currentPendingOrders as $row) {
                // Get the status of the buy order. You can only sell what you got.
                $order = (new \GDAX\Types\Request\Authenticated\Order())->setId($row['order_id']);
                $orderData = $this->client->getOrder($order);

                /** \GDAX\Types\Response\Authenticated\Order $orderData */
                $status = $orderData->getStatus();

                if ($status == 'done') {
                    $buyprice = $row['amount'];
                    $sellPrice = $buyprice + $this->sellspread;
                    if ($startPrice > $sellPrice) {
                        $sellPrice = $startPrice + 0.01;
                    }
                    $sellPrice = number_format($sellPrice, 2);

                    echo 'Sell ' . $this->order_size . ' for ' . $sellPrice . "\n";

                    $order_id = $this->placeSellOrder($row['size'], $sellPrice);

                    if ($order_id) {
                        $this->insertOrder('sell', $order_id, $row['size'], $sellPrice, 'open', $row['id']);

                        echo "Updating order status from pending to done: " . $row['order_id'] . "\n";
                        $this->updateOrderStatus($row['id'], $status);
                    } else {
                        $this->insertOrder('sell', $order_id, $row['size'], $sellPrice, 'rejected', $row['id']);
                    }
                } else {
                    echo "Order not done " . $row['order_id'] . "\n";
                }
            }
        }
    }

    /**
     * Get the last lowest sell price (prevents crosstrading which is not allowed)
     * 
     * @return array
     */
    public function getOpenOrders() {
        $lowestSellPrice = 0;

        $listOrders = (new \GDAX\Types\Request\Authenticated\ListOrders())
                ->setStatus(\GDAX\Utilities\GDAXConstants::ORDER_STATUS_OPEN)
                ->setProductId($this->getProductId());

        $orders = $this->client->getOrders($listOrders);
        if (is_array($orders)) {
            foreach ($orders as $order) {
                $price = $order->getPrice();
                if ($order->getSide() == 'sell') {
                    if ($price < $lowestSellPrice || is_null($lowestSellPrice)) {
                        $lowestSellPrice = $price;
                    }
                } else {
                    $this->pendingBuyPrices[] = $price;
                }
            }
        }
        $restOrders = $this->max_orders - count($orders);
        echo "Maximum number of orders left to place: " . $restOrders . " of " . $this->max_orders . "\n";
        echo "Lowest sellprice " . $lowestSellPrice . "\n";

        return [$restOrders, $lowestSellPrice];
    }

    /**
     * Place a buy order 
     * 
     * @param type $price
     * @return boolean
     */
    protected function placeBuyOrder($price) {
        $order = (new \GDAX\Types\Request\Authenticated\Order())
                ->setType(\GDAX\Utilities\GDAXConstants::ORDER_TYPE_LIMIT)
                ->setProductId($this->getProductId())
                ->setSize($this->order_size)
                ->setSide(\GDAX\Utilities\GDAXConstants::ORDER_SIDE_BUY)
                ->setPrice($price)
                ->setPostOnly(true);

        $response = $this->client->placeOrder($order);

        if (isset($response) && $response->getId() && $response->getMessage() != 'rejected') {
            return $response->getId();
        } else {
            echo "Order not placed because : " . $response->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Checks if there are slots open to place a buy order an if so places x amount of orders
     * 
     * @param int $overrideMaxOrders
     * @return type
     */
    public function buy($overrideMaxOrders = 0) {
        list($restOrders, $lowestSellPrice) = $this->getOpenOrders();

        if ($this->max_orders_per_run > $restOrders) {
            $ordersToPlace = $this->max_orders_per_run;
        } else {
            $ordersToPlace = $restOrders;
        }

        $startPrice = $this->getCurrentPrice();

        if ($startPrice > $this->topBuyingTreshold || $startPrice < $this->bottomBuyingTreshold) {
            printf("Treshold reached %s  [%s]  %s so no buying for now\n", $this->bottomBuyingTreshold, $startPrice, $this->topBuyingTreshold);
            return;
        }

        if ($overrideMaxOrders > 0) {
            $restOrders = $overrideMaxOrders;
        }

        $oldBuyPrice = $startPrice - 0.01;
        for ($i = 1; $i <= $restOrders; $i++) {
            // for buys
            $buyPrice = $oldBuyPrice - $this->spread;
            $buyPrice = number_format($buyPrice, 2);

            // Check if we already have a buy for this price, then try to find an open slot
            $hasBuyPrice = $this->buyPriceExists($buyPrice);
            $n = 1;
            $placeOrder = true;
            while ($hasBuyPrice) {
                $buyPrice = $buyPrice - $n * $this->spread;
                $buyPrice = number_format($buyPrice, 2);

                $hasBuyPrice = $this->buyPriceExists($buyPrice);
                if ($n > 15) {
                    $placeOrder = false;
                    $hasBuyPrice = false;
                }
                $n++;
            }


            if ( (is_null($lowestSellPrice) || $lowestSellPrice == 0  || $buyPrice < $lowestSellPrice) && $placeOrder) {
                echo 'Buy ' . $this->order_size . ' for ' . $buyPrice . "\n";

                $order_id = $this->placeBuyOrder($buyPrice);

                if ($order_id) {
                    $this->insertOrder('buy', $order_id, $this->order_size, $buyPrice);
                } else {
                    $this->insertOrder('buy', $order_id, $this->order_size, $buyPrice, 'rejected');
                    echo "Order not placed for " . $buyPrice . "\n";
                }

                $oldBuyPrice = $buyPrice;
            } else {
                echo "We have open sells that will cross the buys and that is not allowed:" . $buyPrice . "\n";
            }
        }
    }

    /**
     * Main entry point
     */
    public function run() {
        $this->deleteOrdersWithoutOrderId();

        $this->actualize();
        $this->actualizeSells();
        $this->sell();


        $this->actualizeBuys();
        $this->cancelOldBuyOrders();
        $this->buy();


        echo "\nDONE " . date('Y-m-d H:i:s') . "\n";
    }

}
