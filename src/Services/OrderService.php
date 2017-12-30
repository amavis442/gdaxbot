<?php

namespace App\Services;

use App\Contracts\OrderServiceInterface;

class OrderService implements OrderServiceInterface {

    protected $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function createDatabase() {

        $sql = "CREATE TABLE orders (id INTEGER PRIMARY KEY AUTO_INCREMENT, parent_id integer, side varchar(10), size varchar(20), amount decimal(15,9),status varchar(40), order_id varchar(40), created_at datetime, updated_at timestamp);";
        $stmt = $this->conn->prepare($sql);
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
    public function insertOrder($side, $order_id, $size, $amount, $status = 'pending', $parent_id = 0): int {
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
    public function garbageCollection() {
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
    public function getPendingBuyOrders(): array {
        $sql = "SELECT * FROM orders WHERE side='buy' AND (status = 'pending' or status='open')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetchAll();
        if (is_array($result)) {
            return $result;
        } else {
            return [];
        }
    }

    /**
     * Fetch all orders that have given status
     * 
     * @param string $status
     * @return array
     */
    public function fetchAllOrders($status = 'pending'): array {
        $sql = 'SELECT * FROM orders WHERE status = :status';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('status', $status);
        $stmt->execute();

        $result = $stmt->fetchAll();
        if (is_array($result)) {
            return $result;
        } else {
            return [];
        }
    }

    /**
     * Fetch inidividual order 
     * 
     * @param int $id
     * @return array
     */
    public function fetchOrder($id): array {
        $sql = 'SELECT * FROM orders WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('id', $id);
        $stmt->execute();

        $result = $stmt->fetch();
        if (is_array($result)) {
            return $result;
        } else {
            return [];
        }
    }

    /**
     * Fetch order by order id from coinbase (has the form of aaaaa-aaaa-aaaa-aaaaa)
     * 
     * @param type $order_id
     * @return type
     */
    public function fetchOrderByOrderId($order_id): array {
        $sql = 'SELECT * FROM orders WHERE order_id = :orderid';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('orderid', $order_id);
        $stmt->execute();

        $result = $stmt->fetch();
        if (is_array($result)) {
            return $result;
        } else {
            return [];
        }
    }

    public function getNumOpenOrders(): int {
        $sql = "SELECT count(*) total FROM orders WHERE status = 'open' OR status = 'pending'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return isset($result['total']) ? $result['total'] : 0;
    }
    
    public function getLowestSellPrice() {
        $sql = "SELECT min(amount) minprice FROM orders WHERE side='sell' AND status = 'open' OR status = 'pending'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return isset($result['minprice']) ? $result['minprice'] : 0.0;
    }

    /**
     * 
     * @param string $side
     * @param string $status
     * @return array
     */
    public function getOrdersBySide($side, $status = 'pending'): array {
        $sql = "SELECT * FROM orders WHERE side = :side AND status = :status";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('side', $side);
        $stmt->bindValue('status', $status);
        $stmt->execute();

        $result = $stmt->fetchAll();
        if (is_array($result)) {
            return $result;
        } else {
            return [];
        }
    }

    /**
     * Get the open sell orders
     * 
     * @return array
     */
    public function getOpenSellOrders(): array {
        $sql = "SELECT * FROM orders WHERE side = :side AND (status = 'pending' OR status = 'open')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('side', 'sell');
        $stmt->execute();

        $result = $stmt->fetchAll();
        if (is_array($result)) {
            return $result;
        } else {
            return [];
        }
    }

    /**
     * Get the open buy orders
     * 
     * @return array
     */
    public function getOpenBuyOrders(): array {
        $sql = "SELECT * FROM orders WHERE side = :side AND (status = 'pending' OR status = 'open')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('side', 'buy');
        $stmt->execute();

        $result = $stmt->fetchAll();
        if (is_array($result)) {
            return $result;
        } else {
            return [];
        }
    }

    /**
     * Check of we already have a open buy order with that price
     * 
     * @param type $price
     * @return boolean
     */
    public function buyPriceExists($price): bool {
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
     * Sometimes the price fluctuates to much and can result in a rejected order.
     * 
     */
    public function fixRejectedSells() {
        $sql = "SELECT * FROM orders WHERE status = 'rejected' AND side='sell' AND parent_id > 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();

        foreach ($result as $row) {
            $sql = "UPDATE orders SET status='open' WHERE id=:id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue('id', $row['parent_id']);
            $stmt->execute();

            $sql = "UPDATE orders SET status='fixed' WHERE id=:id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue('id', $row['id']);
            $stmt->execute();
        }
    }

    /**
     * Orders can also be inserted by hand
     * 
     * @param type $orders
     */
    public function fixUnknownOrdersFromGdax($orders) {
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

    public function getProfits(string $date = null): array {
        if (is_null($date)) {
            $date = date('Y-m-d');
        }
        $date .= ' 00:00:00';
        
        $sql = "SELECT b.created_at,b.side as buyside,b.size buysize,b.amount buyamount,s.side sellside,s.size sellsize,s.amount sellamount, (s.amount - b.amount) * s.size as profit FROM orders s, ".
                "(SELECT * FROM orders WHERE side='buy' AND `status`='done') b ".
                " WHERE s.side='sell' AND s.status='done' AND b.id = s.parent_id AND b.created_at >= :createdat";
    
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('createdat', $date);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        if ($rows) {
            return $rows;
        } else {
            return [];
        }
    }
    
}
