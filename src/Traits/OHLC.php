<?php

/**
 * Created by PhpStorm.
 * User: joeldg
 * Date: 6/26/17
 * Time: 4:03 PM
 */

namespace App\Traits;

use App\Util\Transform;
use Illuminate\Database\Capsule\Manager as DB;
use \App\Util\Cache;

trait OHLC
{

    /**
     * @param $ticker
     *
     * @return bool
     */
    /**
     * @param array $ticker
     *
     * @return bool
     */
    public function markOHLC(array $ticker): bool
    {
        $timeid = date('YmdHis'); // 20170530152259 unique for date

        $last_price = $ticker['best_bid'];
        $product_id = $ticker['product_id'];
        $volume     = $ticker['volume_24h'];

        /** tick table update */
        DB::insert("
            INSERT INTO ohlc_tick
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $last_price, $last_price, $last_price, $last_price, $volume)
            ON DUPLICATE KEY UPDATE
            `high`   = CASE WHEN `high` < VALUES(`high`) THEN VALUES(`high`) ELSE `high` END,
            `low`    = CASE WHEN `low` > VALUES(`low`) THEN VALUES(`low`) ELSE `low` END,
            `volume` = VALUES(`volume`),
            `close`  = VALUES(`close`)
        ");

        $timeid = date("YmdHi", strtotime($timeid));

        $this->update1MinuteOHLC($product_id, $timeid, $volume);
        $this->update5MinuteOHLC($product_id, $timeid, $volume);
        $this->update15MinutOHLC($product_id, $timeid, $volume);
        $this->update30MinuteOHLC($product_id, $timeid, $volume);
        $this->update1HourOHLC($product_id, $timeid, $volume);


        return true;
    }

    /**
     * @param string $product_id
     * @param int    $timeid
     * @param int    $volume
     */
    public function update1MinuteOHLC(string $product_id, int $timeid, int $volume = 0)
    {
        $open1  = null;
        $close1 = null;
        $high1  = null;
        $low1   = null;


        $last1m = DB::table('ohlc_1m')->select(DB::raw('MAX(timeid) AS timeid'))
                    ->where('product_id', $product_id)
                    ->first();

        if ($last1m) {
            $last1timeid = $last1m->timeid;
            $last1timeid = date("YmdHi", strtotime($last1timeid));
        }

        if ($last1timeid < $timeid) {

            /* Get High and Low from ticker data for insertion */
            $last1timeids = date("YmdHis", strtotime(date("YmdHi", strtotime("-1 minutes", strtotime("now")))));
            $accum1ma     = DB::table('ohlc_tick')->select(DB::raw('MAX(high) as high, MIN(low) as low'))
                              ->where('product_id', $product_id)
                              ->where('timeid', '>=', $last1timeids)
                              ->where('timeid', '<=', ($last1timeids + 59))
                              ->first();

            $high1 = $accum1ma->high;
            $low1  = $accum1ma->low;


            /* Get Open price from ticker data and last minute */
            $accum1mb = DB::table('ohlc_tick')->select(DB::raw('open AS open'))
                          ->where('product_id', $product_id)
                          ->where('timeid', '>=', $last1timeids)
                          ->where('timeid', '<=', ($last1timeids + 59))
                          ->limit(1)
                          ->first();

            if ($accum1mb) {
                $open1 = $accum1mb->open;
            }

            /* Get close price from ticker data and last minute */
            $accum1mc = DB::table('ohlc_tick')->select(DB::raw('close AS close'))
                          ->where('product_id', $product_id)
                          ->where('timeid', '>=', $last1timeids)
                          ->where('timeid', '<=', ($last1timeids + 59))
                          ->orderBy('ctime', 'desc')
                          ->limit(1)
                          ->first();

            if ($accum1mc) {
                $close1 = $accum1mc->close;
            }

            if ($open1 && $close1 && $high1 && $low1) {
                DB::insert("
            INSERT INTO ohlc_1m 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open1, $high1, $low1, $close1, $volume)
            ON DUPLICATE KEY UPDATE 
            `high`   = CASE WHEN `high` < VALUES(`high`) THEN VALUES(`high`) ELSE `high` END,
            `low`    = CASE WHEN `low` > VALUES(`low`) THEN VALUES(`low`) ELSE `low` END,
            `volume` = VALUES(`volume`),
            `close`  = VALUES(`close`)");
            }
        }
    }

    /**
     * @param string $product_id
     * @param int    $timeid
     * @param int    $volume
     */
    public function update5MinuteOHLC(string $product_id, int $timeid, int $volume = 0)
    {
        $open5  = null;
        $close5 = null;
        $high5  = null;
        $low5   = null;

        $last5m = DB::table('ohlc_5m')->select(DB::raw('MAX(timeid) AS timeid'))
                    ->where('product_id', $product_id)
                    ->first();

        $last5timeid = $last5m->timeid;
        $last5timeid = date("YmdHi", strtotime("+4 minutes", strtotime($last5timeid)));


        if ($last5timeid < $timeid) {
            /* Get High and Low from 1m data for insertion */
            $last5timeids = date("YmdHi", strtotime("-5 minutes", strtotime("now")));
            $accum5ma     = DB::table('ohlc_1m')->select(DB::raw('MAX(high) as high, MIN(low) as low'))
                              ->where('product_id', $product_id)
                              ->where('timeid', '>=', $last5timeids)
                              ->where('timeid', '<=', ($timeid))
                              ->first();
            if ($accum5ma) {
                $high5 = $accum5ma->high;
                $low5  = $accum5ma->low;
            }

            /* Get Open price from 1m data and last 5 minutes */
            $accum5mb = DB::table('ohlc_1m')->select(DB::raw('*'))
                          ->where('product_id', $product_id)
                          ->where('timeid', '>=', $last5timeids)
                          ->where('timeid', '<=', ($timeid))
                          ->limit(1)
                          ->first();

            if ($accum5mb) {
                $open5 = $accum5mb->open;
            }

            /* Get Close price from 1m data and last 5 minutes */
            $accum5mc = DB::table('ohlc_1m')->select(DB::raw('*'))
                          ->where('product_id', $product_id)
                          ->where('timeid', '>=', $last5timeids)
                          ->where('timeid', '<=', ($timeid))
                          ->orderBy('ctime', 'desc')
                          ->limit(1)
                          ->first();

            if ($accum5mc) {
                $close5 = $accum5mc->close;
            }

            if ($open5 && $close5 && $low5 && $high5) {
                DB::insert("
            INSERT INTO ohlc_5m 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open5, $high5, $low5, $close5, $volume)
            ON DUPLICATE KEY UPDATE 
            `high`   = CASE WHEN `high` < VALUES(`high`) THEN VALUES(`high`) ELSE `high` END,
            `low`    = CASE WHEN `low` > VALUES(`low`) THEN VALUES(`low`) ELSE `low` END,
            `volume` = VALUES(`volume`),
            `close`  = VALUES(`close`)");
            }
        }
    }

    /**
     * @param string $product_id
     * @param int    $timeid
     * @param int    $volume
     */
    public function update15MinutOHLC(string $product_id, int $timeid, int $volume = 0)
    {
        /** 15m table update * */
        $open15  = null;
        $close15 = null;
        $high15  = null;
        $low15   = null;

        $last15m = DB::table('ohlc_15m')->select(DB::raw('MAX(timeid) AS timeid'))
                     ->where('product_id', $product_id)
                     ->first();

        if ($last15m) {
            $last15timeid = $last15m->timeid;
            $last15timeid = date("YmdHi", strtotime("+14 minutes", strtotime($last15timeid)));
        }

        if ($last15timeid < $timeid) {
            /* Get High and Low from 5m data for insertion */
            $last15timeids = date("YmdHi", strtotime("-15 minutes", strtotime("now")));
            $accum15ma     = DB::table('ohlc_5m')->select(DB::raw('MAX(high) as high, MIN(low) as low'))
                               ->where('product_id', $product_id)
                               ->where('timeid', '>=', $last15timeids)
                               ->where('timeid', '<=', ($timeid))
                               ->first();

            if ($accum15ma) {
                $high15 = $accum15ma->high;
                $low15  = $accum15ma->low;
            }

            /* Get Open price from 5m data and last 15 minutes */
            $accum15mb = DB::table('ohlc_5m')->select(DB::raw('*'))
                           ->where('product_id', $product_id)
                           ->where('timeid', '>=', $last15timeids)
                           ->where('timeid', '<=', ($timeid))
                           ->limit(1)
                           ->first();

            if ($accum15mb) {
                $open15 = $accum15mb->open;
            }

            /* Get Close price from 5m data and last 15 minutes */
            $accum15mc = DB::table('ohlc_5m')->select(DB::raw('*'))
                           ->where('product_id', $product_id)
                           ->where('timeid', '>=', $last15timeids)
                           ->where('timeid', '<=', ($timeid))
                           ->orderBy('ctime', 'desc')
                           ->limit(1)
                           ->first();

            if ($accum15mc) {
                $close15 = $accum15mc->close;
            }

            if ($open15 && $close15 && $low15 && $high15) {
                DB::insert("
            INSERT INTO ohlc_15m 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open15, $high15, $low15, $close15, $volume)
            ON DUPLICATE KEY UPDATE 
            `high`   = CASE WHEN `high` < VALUES(`high`) THEN VALUES(`high`) ELSE `high` END,
            `low`    = CASE WHEN `low` > VALUES(`low`) THEN VALUES(`low`) ELSE `low` END,
            `volume` = VALUES(`volume`),
            `close`  = VALUES(`close`)");
            }
        }
    }

    /**
     * @param string $product_id
     * @param int    $timeid
     * @param int    $volume
     */
    public function update30MinuteOHLC(string $product_id, int $timeid, int $volume = 0)
    {
        /** 30m table update * */
        $open30  = null;
        $close30 = null;
        $high30  = null;
        $low30   = null;

        $last30m = DB::table('ohlc_30m')->select(DB::raw('MAX(timeid) AS timeid'))
                     ->where('product_id', $product_id)
                     ->first();

        if ($last30m) {
            $last30timeid = $last30m->timeid;
            $last30timeid = date("YmdHi", strtotime("+29 minutes", strtotime($last30timeid)));
        }

        if ($last30timeid < $timeid) {
            /* Get High and Low from 15m data for insertion */
            $last30timeids = date("YmdHi", strtotime("-30 minutes", strtotime("now")));
            $accum30ma     = DB::table('ohlc_15m')->select(DB::raw('MAX(high) as high, MIN(low) as low'))
                               ->where('product_id', $product_id)
                               ->where('timeid', '>=', $last30timeids)
                               ->where('timeid', '<=', ($timeid))
                               ->first();

            if ($accum30ma) {
                $high30 = $accum30ma->high;
                $low30  = $accum30ma->low;
            }

            /* Get Open price from 15m data and last 30 minutes */
            $accum30mb = DB::table('ohlc_15m')->select(DB::raw('*'))
                           ->where('product_id', $product_id)
                           ->where('timeid', '>=', $last30timeids)
                           ->where('timeid', '<=', ($timeid))
                           ->limit(1)
                           ->first();

            if ($accum30mb) {
                $open30 = $accum30mb->open;
            }

            /* Get Close price from 15m data and last 30 minutes */
            $accum30mc = DB::table('ohlc_15m')->select(DB::raw('*'))
                           ->where('product_id', $product_id)
                           ->where('timeid', '>=', $last30timeids)
                           ->where('timeid', '<=', ($timeid))
                           ->orderBy('ctime', 'desc')
                           ->limit(1)
                           ->first();

            if ($accum30mc) {
                $close30 = $accum30mc->close;
            }

            if ($open30 && $close30 && $low30 && $high30) {
                DB::insert("
            INSERT INTO ohlc_30m 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open30, $high30, $low30, $close30, $volume)
            ON DUPLICATE KEY UPDATE 
            `high`   = CASE WHEN `high` < VALUES(`high`) THEN VALUES(`high`) ELSE `high` END,
            `low`    = CASE WHEN `low` > VALUES(`low`) THEN VALUES(`low`) ELSE `low` END,
            `volume` = VALUES(`volume`),
            `close`  = VALUES(`close`)");
            }
        }
    }

    /**
     * @param string $product_id
     * @param int    $timeid
     * @param int    $volume
     */
    public function update1HourOHLC(string $product_id, int $timeid, int $volume = 0)
    {
        /** 1h table update * */
        $open60  = null;
        $close60 = null;
        $high60  = null;
        $low60   = null;

        $last60m = DB::table('ohlc_1h')->select(DB::raw('MAX(timeid) AS timeid'))
                     ->where('product_id', $product_id)
                     ->first();

        if ($last60m) {
            $last60timeid = $last60m->timeid;
            $last60timeid = date("YmdHi", strtotime("+59 minutes", strtotime($last60timeid)));
        }

        if ($last60timeid < $timeid) {
            /* Get High and Low from 30m data for insertion */
            $last60timeids = date("YmdHi", strtotime("-60 minutes", strtotime("now")));
            $accum60ma     = DB::table('ohlc_30m')->select(DB::raw('MAX(high) as high, MIN(low) as low'))
                               ->where('product_id', $product_id)
                               ->where('timeid', '>=', $last60timeids)
                               ->where('timeid', '<=', ($timeid))
                               ->first();

            if ($accum60ma) {
                $high60 = $accum60ma->high;
                $low60  = $accum60ma->low;
            }

            /* Get Open price from 30m data and last 60 minutes */
            $accum60mb = DB::table('ohlc_30m')->select(DB::raw('*'))
                           ->where('product_id', $product_id)
                           ->where('timeid', '>=', $last60timeids)
                           ->where('timeid', '<=', ($timeid))
                           ->limit(1)
                           ->first();

            if ($accum60mb) {
                $open60 = $accum60mb->open;
            }

            /* Get Close price from 30m data and last 60 minutes */
            $accum60mc = DB::table('ohlc_30m')->select(DB::raw('*'))
                           ->where('product_id', $product_id)
                           ->where('timeid', '>=', $last60timeids)
                           ->where('timeid', '<=', ($timeid))
                           ->orderBy('ctime', 'desc')
                           ->limit(1)
                           ->first();

            if ($accum60mc) {
                $close60 = $accum60mc->close;
            }

            if ($open60 && $close60 && $low60 && $high60) {
                DB::insert("
            INSERT INTO ohlc_1h 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open60, $high60, $low60, $close60, $volume)
            ON DUPLICATE KEY UPDATE 
            `high`   = CASE WHEN `high` < VALUES(`high`) THEN VALUES(`high`) ELSE `high` END,
            `low`    = CASE WHEN `low` > VALUES(`low`) THEN VALUES(`low`) ELSE `low` END,
            `volume` = VALUES(`volume`),
            `close`  = VALUES(`close`)");
            }
        }
    }

    /**
     * Transform data for the trader functions
     * vb array trader_cdl2crows ( array $open , array $high , array $low , array $close )
     *
     * @param array $datas
     *
     * @return array
     */
    public function transformPairData(array $datas): array
    {
        $ret['date']   = [];
        $ret['low']    = [];
        $ret['high']   = [];
        $ret['open']   = [];
        $ret['close']  = [];
        $ret['volume'] = [];

        $ret = [];

        foreach ($datas as $data) {
            $ret['date'][]   = $data->buckettime;
            $ret['low'][]    = $data->low;
            $ret['high'][]   = $data->high;
            $ret['open'][]   = $data->open;
            $ret['close'][]  = $data->close;
            $ret['volume'][] = $data->volume;
        }

        foreach ($ret as $key => $rettemmp) {
            $ret[$key] = array_reverse($rettemmp);
        }

        return $ret;
    }

    /**
     * @param string $product_id
     * @param int    $limit
     * @param bool   $day_data
     * @param int    $hour
     * @param string $periodSize
     * @param bool   $returnResultSet
     *
     * @return array|\Illuminate\Support\Collection|null
     */
    public function getRecentData(string $product_id = 'BTC-EUR', int $limit = 168, bool $day_data = false, int $hour = 12, string $periodSize = '1m', bool $returnResultSet = false)
    {
        /**
         *  we need to cache this as many strategies will be
         *  doing identical pulls for signals.
         */
        $key   = 'recent.' . $product_id . '.' . $limit . ".$day_data.$hour.$periodSize";
        $value = Cache::get($key);


        if ($value) {
            return $value;
        }

        $rows = DB::table('ohlc_' . $periodSize)
                  ->select(DB::raw('*, unix_timestamp(ctime) as buckettime'))
                  ->where('product_id', $product_id)
                  ->orderby('timeid', 'DESC')
                  ->limit($limit)
                  ->get();

        $rows = Transform::toArray($rows);

        $starttime    = null;
        $validperiods = 0;
        $oldrow       = null;
        foreach ($rows as $row) {

            $endtime = $row->buckettime;

            if ($starttime == null) {
                $starttime = $endtime;
                echo "Starting at " . $row->ctime . "...\n";
            } else {
                /** Check for missing periods * */
                if ($periodSize == '1m') {
                    $variance = (int)119;
                } else {
                    if ($periodSize == '5m') {
                        $variance = (int)375;
                    } else {
                        if ($periodSize == '15m') {
                            $variance = (int)1125;
                        } else {
                            if ($periodSize == '30m') {
                                $variance = (int)2250;
                            } else {
                                if ($periodSize == '1h') {
                                    $variance = (int)4500;
                                } else {
                                    if ($periodSize == '1d') {
                                        $variance = (int)108000;
                                    }
                                }
                            }
                        }
                    }
                }

                $periodcheck = $starttime - $endtime;

                if ((int)$periodcheck > (int)$variance) {
                    dump($starttime, $endtime, $periodcheck, $oldrow, $row);
                    throw new \RuntimeException('YOU HAVE ' . $validperiods . ' PERIODS OF VALID PRICE DATA OUT OF ' . $limit . '. Please ensure price sync is running and wait for additional data to be logged before trying again. Additionally you could use a smaller time period if available.');
                }

                $validperiods++;
            }
            $starttime = $endtime;
            $oldrow    = $row;
        }

        if ($returnResultSet) {
            $ret = $rows;
        } else {
            $ret = $this->transformPairData($rows);
        }

        Cache::put($key, $ret, 60);

        return $ret;
    }

}
