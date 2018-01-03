<?php

namespace App\Services;

use App\Contracts\OrderServiceInterface;
use Illuminate\Database\Capsule\Manager as DB;
use App\Util\Transform;


class OrderService implements OrderServiceInterface
{

    public function purgeDatabase()
    {
        DB::table('orders')->delete();
    }

    public function deleteOrder($id)
    {
        DB::table('orders')->delete($id);
    }

    public function updateOrder($id, $side)
    {
        DB::table('orders')->where('id', $id)->update(['side' => $side]);
    }

    public function updateOrderStatus($id, $status)
    {
        DB::table('orders')->where('id', $id)->update(['status' => $status]);
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
    public function insertOrder($side, $order_id, $size, $amount, $status = 'pending', $parent_id = 0): int
    {
        $id = DB::table('orders')->insertGetId([
            'side'       => $side,
            'order_id'    => $order_id,
            'size'       => $size,
            'amount'     => $amount,
            'status'     => $status,
            'parent_id'  => $parent_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $id;
    }

    /**
     * Get rid of the failures.
     */
    public function garbageCollection()
    {
        DB::table('orders')->where('order_id', '')->where('status', '<>', 'deleted')->update(['status' => 'deleted']);
    }

    

    /**
     * Get the open buy orders
     * 
     * @return array
     */
    public function getPendingBuyOrders(): array
    {
        $result      = DB::table('orders')->select('*')->where('side', 'buy')->where('status', 'IN', ['pending', 'open'])->get();
        
        return Transform::toArray($result);
    }

    /**
     * Fetch all orders that have given status
     * 
     * @param string $status
     * @return array
     */
    public function fetchAllOrders($status = 'pending'): array
    {
        $result      = DB::table('orders')->select('*')->where('status', $status)->get();
        
        return Transform::toArray($result);
    }

    /**
     * Fetch inidividual order 
     * 
     * @param int $id
     * @return array
     */
    public function fetchOrder($id): stdClass
    {
        $result = DB::table('orders')->select('*')->where('id', $id)->first();
        
        return $result;
    }

    /**
     * Fetch order by order id from coinbase (has the form of aaaaa-aaaa-aaaa-aaaaa)
     * 
     * @param type $order_id
     * @return type
     */
    public function fetchOrderByOrderId($order_id): stdClass
    {
        $result = DB::table('orders')->select('*')->where('order_id', $order_id)->first();

        return $result;
    }

    public function getNumOpenOrders(): int
    {
        $result = DB::table('orders')->select(DB::raw('count(*) total'))->where('status', 'open')->orWhere('status', 'pending')->first();

        return isset($result->total) ? $result->total : 0;
    }

    public function getLowestSellPrice()
    {
        $result = DB::select("SELECT min(amount) minprice FROM orders WHERE side='sell' AND status = 'open' OR status = 'pending'");
        $row = $result[0];
        
        return isset($row->minprice) ? $row->minprice : 0.0;
    }

    /**
     * 
     * @param string $side
     * @param string $status
     * @return array
     */
    public function getOrdersBySide($side, $status = 'pending'): array
    {
        $result =  DB::table('order')->where('side',$side)->where('status', $status)->get();
    
        return Transform::toArray($result);
    }

    /**
     * Get the open sell orders
     * 
     * @return array
     */
    public function getOpenSellOrders(): array
    {
        $result = DB::select("SELECT * FROM orders WHERE side = 'sell' AND (status = 'pending' OR status = 'open')");
        
        return Transform::toArray($result);
    }

    /**
     * Get the open buy orders
     * 
     * @return array
     */
    public function getOpenBuyOrders(): array
    {
        $result =  DB::select("SELECT * FROM orders WHERE side = 'buy' AND (status = 'pending' OR status = 'open')");
        
        return Transform::toArray($result);
    }

    /**
     * Check of we already have a open buy order with that price
     * 
     * @param type $price
     * @return boolean
     */
    public function buyPriceExists($price): bool
    {
        $result = DB::select("SELECT * FROM orders WHERE side = 'buy' AND (status = 'pending' OR status = 'open') AND amount = $price");
        foreach ($result as $row) {
            if ($row->amount) {
                return true;
            }
        }
        return false;
    }

    /**
     * 
     */
    public function listRowsFromDatabase()
    {
        $currentPendingOrders = $this->fetchAllOrders();
        foreach ($currentPendingOrders as $row) {
            printf("%s| %s| %s| %s\n", $row->created_at, $row->side, $row->amount, $row->order_id);
        }
    }

    /**
     * Sometimes the price fluctuates to much and can result in a rejected order.
     * 
     */
    public function fixRejectedSells()
    {
        $result = DB::select("SELECT * FROM orders WHERE status = 'rejected' AND side='sell' AND parent_id > 0");

        foreach ($result as $row) {
            DB::table('orders')->where('id', $row->parent_id)->update(['status' => 'open']);
            DB::table('orders')->where('id', $row->id)->update(['status' => 'fixed']);
        }
    }

    /**
     * Orders can also be inserted by hand
     * 
     * @param type $orders
     */
    public function fixUnknownOrdersFromGdax($orders)
    {
        if (is_array($orders)) {
            foreach ($orders as $order) {
                $order_id = $order->getId();
                $row      = $this->fetchOrderByOrderId($order_id);
                if (!$row) {
                    $this->insertOrder($order->getSide(), $order->getId(), $order->getSize(), $order->getPrice());
                } else {
                    if ($row->status != 'done') {
                        $this->updateOrderStatus($row->id, $order->getStatus());
                    }
                }
            }
        }
    }

    public function getProfits(string $date = null): array
    {
        if (is_null($date)) {
            $date = date('Y-m-d');
        }
        $date .= ' 00:00:00';

        $result = DB::select("SELECT b.created_at buydate,b.side as buyside,b.size buysize,b.amount buyamount,s.created_at selldate,s.side sellside,s.size sellsize,s.amount sellamount, (s.amount - b.amount) * s.size as profit FROM orders s, " .
                        "(SELECT * FROM orders WHERE side='buy' AND `status`='done') b " .
                        " WHERE s.side='sell' AND s.status='done' AND b.id = s.parent_id AND b.created_at >= '$date'");

        return Transform::toArray($result);
    }

}
