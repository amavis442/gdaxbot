<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\PositionServiceInterface;
use Illuminate\Database\Capsule\Manager as DB;
use App\Util\Transform;

/**
 * Class OrderService
 *
 * @package App\Services
 */
class PositionService implements PositionServiceInterface
{
    /**
     *
     */
    public function purgeDatabase()
    {
        DB::table('positions')->delete();
    }

    /**
     * @param int $id
     *
     * @return mixed|void
     */
    public function delete(int $id)
    {
        DB::table('positions')->delete($id);
    }

    /**
     * 
     */
    public function open(string $order_id, float $size, float $amount): int
    {
        $id = DB::table('positions')->insertGetId([
                                                   'order_id'    => $order_id,
                                                   'size'        => $size,
                                                   'amount'      => $amount,
                                                   'position'      => 'open',
                                                   'created_at'  => date('Y-m-d H:i:s')
                                               ]);

        return $id;
    }

    public function pending(int $id)
    {
        DB::table('positions')->where('id', $id)->update(['position' => 'pending']);
    }

    public function close(int $id)
    {
        DB::table('positions')->where('id', $id)->update(['position' => 'closed']);
    }

    /**
     * Fetch all orders that have given status
     *
     * @param string $status
     *
     * @return array
     */
    public function fetchAll(string $status = 'open'): array
    {
        $result = DB::table('positions')->select('*')->where('position', $status)->get();

        return Transform::toArray($result);
    }

    /**
     * @param int $id
     *
     * @return \stdClass
     */
    public function fetch(int $id): ?\stdClass
    {
        $result = DB::table('positions')->select('*')->where('id', $id)->first();

        if ($result) {
            return $result;
        } else {
            return null;
        }
    }

    /**
     * Fetch order by order id from coinbase (has the form of aaaaa-aaaa-aaaa-aaaaa)
     *
     * @param string $order_id
     *
     * @return \stdClass
     */
    public function fetchByOrderId(string $order_id): ?\stdClass
    {
        $result = DB::table('positions')->select('*')->where('order_id', $order_id)->first();
        if ($result) {
            return $result;
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getNumOpen(): int
    {
        $result = DB::table('positions')->select(DB::raw('count(*) total'))->where('position', 'open')->first();

        return isset($result->total) ? $result->total : 0;
    }

   
    /**
     * Get the open sell orders
     *
     * @return array
     */
    public function getOpen(): array
    {
        $result = DB::select("SELECT * FROM positions WHERE position = 'open'");

        return Transform::toArray($result);
    }

    public function getClosed(): array
    {
        $result = DB::select("SELECT * FROM positions WHERE position = 'closed'");

        return Transform::toArray($result);
    }
}
