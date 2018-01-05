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

        $timeidTicker = $ticker['timeid'];   //date('YmdHis'); // 20170530152259 unique for date
        $last_price   = (float) $ticker['price'];
        $product_id   = $ticker['product_id'];
        $volume       = round($ticker['volume']);

        /** tick table update */
        DB::insert("INSERT INTO ohlc_tick
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeidTicker, $last_price, $last_price, $last_price, $last_price, $volume)
            ON DUPLICATE KEY UPDATE
            `high`   = CASE WHEN `high` < VALUES(`high`) THEN VALUES(`high`) ELSE `high` END,
            `low`    = CASE WHEN `low` > VALUES(`low`) THEN VALUES(`low`) ELSE `low` END,
            `volume` = VALUES(`volume`),
            `close`  = VALUES(`close`)");

        $timeid = \Carbon\Carbon::createFromFormat('YmdHis', $timeidTicker)->format('YmdHi');

        $this->update1MinuteOHLC($product_id, $timeid);
        $this->update5MinuteOHLC($product_id, $timeid);
        $this->update15MinutOHLC($product_id, $timeid);
        $this->update30MinuteOHLC($product_id, $timeid);
        $this->update1HourOHLC($product_id, $timeid);

        return true;
    }

    /**
     * @param string $product_id
     * @param int    $timeid
     * @param int    $volume
     */
    public function update1MinuteOHLC(string $product_id, int $timeid)
    {
        $open   = null;
        $close  = null;
        $high   = null;
        $low    = null;
        $volume = null;

        $lastRecordedTimeId = DB::table('ohlc_1m')->select(DB::raw('MAX(timeid) AS timeid'))
                ->where('product_id', $product_id)
                ->first();

        if ($lastRecordedTimeId && $lastRecordedTimeId->timeid) {
            $lasttimeidRecorded = (int) \Carbon\Carbon::createFromFormat('YmdHi', $lastRecordedTimeId->timeid)->format('YmdHi');
        } else {
            $lasttimeidRecorded = 0;
        }

        if ($lasttimeidRecorded < $timeid) {

            /* Get High and Low from ticker data for insertion */
            $starttimeid = (int) (\Carbon\Carbon::now()->subMinute(1)->format('YmdHi') . '00');
            $lasttimeid  = (int) $starttimeid + 59;


            $accumHighLowVolume = DB::table('ohlc_tick')->select(DB::raw('MAX(high) as high, MIN(low) as low, AVG(volume) as volume'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', $lasttimeid)
                    ->first();

            $high   = (float) $accumHighLowVolume->high;
            $low    = (float) $accumHighLowVolume->low;
            $volume = (int) round($accumHighLowVolume->volume);

            /* Get Open price from ticker data and last minute */
            $accumOpen = DB::table('ohlc_tick')->select(DB::raw('open AS open'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', $lasttimeid)
                    ->limit(1)
                    ->first();

            if ($accumOpen) {
                $open = (float) $accumOpen->open;
            }

            /* Get close price from ticker data and last minute */
            $accumClose = DB::table('ohlc_tick')->select(DB::raw('close AS close'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', $lasttimeid)
                    ->orderBy('ctime', 'desc')
                    ->limit(1)
                    ->first();

            if ($accumClose) {
                $close = (float) $accumClose->close;
            }

            if ($open && $close && $high && $low) {
                DB::insert("INSERT INTO ohlc_1m 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open, $high, $low, $close, $volume)
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
        $open   = null;
        $close  = null;
        $high   = null;
        $low    = null;
        $volume = null;


        $lastRecordedTimeId = DB::table('ohlc_5m')->select(DB::raw('MAX(timeid) AS timeid'))
                ->where('product_id', $product_id)
                ->first();

        if ($lastRecordedTimeId && $lastRecordedTimeId->timeid) {
            $lasttimeid = (int) \Carbon\Carbon::createFromFormat('YmdHi', $lastRecordedTimeId->timeid)->addMinute(4)->format('YmdHi');
        } else {
            $lasttimeid = 0;
        }

        if ($lasttimeid < $timeid) {

            /* Get High and Low from 1m data for insertion */
            $starttimeid = \Carbon\Carbon::now()->subMinute(5)->format('YmdHi');


            $accumHighLowVolume = DB::table('ohlc_1m')->select(DB::raw('MAX(high) as high, MIN(low) as low, AVG(volume) as volume'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', $timeid)
                    ->first();

            if ($accumHighLowVolume) {
                $high   = (float) $accumHighLowVolume->high;
                $low    = (float) $accumHighLowVolume->low;
                $volume = (int) round($accumHighLowVolume->volume);
            }

            /* Get Open price from 1m data and last 5 minutes */
            $accumOpen = DB::table('ohlc_1m')->select(DB::raw('*'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', $timeid)
                    ->limit(1)
                    ->first();

            if ($accumOpen) {
                $open = (float) $accumOpen->open;
            }

            /* Get Close price from 1m data and last 5 minutes */
            $accumClose = DB::table('ohlc_1m')->select(DB::raw('*'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->orderBy('ctime', 'desc')
                    ->limit(1)
                    ->first();

            if ($accumClose) {
                $close = (float) $accumClose->close;
            }

            if ($open && $close && $low && $high) {
                DB::insert("INSERT INTO ohlc_5m 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open, $high, $low, $close, $volume)
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
        $open   = null;
        $close  = null;
        $high   = null;
        $low    = null;
        $volume = null;

        $lastRecordedTimeId = DB::table('ohlc_15m')->select(DB::raw('MAX(timeid) AS timeid'))
                ->where('product_id', $product_id)
                ->first();

        if ($lastRecordedTimeId && $lastRecordedTimeId->timeid) {
            $lasttimeid = (int) \Carbon\Carbon::createFromFormat('YmdHi', $lastRecordedTimeId->timeid)->addMinute(14)->format('YmdHi');
        } else {
            $lasttimeid = 0;
        }

        if ($lasttimeid < $timeid) {
            /* Get High and Low from 5m data for insertion */
            $starttimeid = \Carbon\Carbon::now()->subMinute(15)->format('YmdHi'); //last15timeids = date("YmdHi", strtotime("-15 minutes", strtotime("now")));

            $accumOpenHighVolume = DB::table('ohlc_5m')->select(DB::raw('MAX(high) as high, MIN(low) as low, AVG(volume) as volume'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->first();

            if ($accumOpenHighVolume) {
                $high   = (float) $accumOpenHighVolume->high;
                $low    = (float) $accumOpenHighVolume->low;
                $volume = (int) round($volume);
            }

            /* Get Open price from 5m data and last 15 minutes */
            $accumOpen = DB::table('ohlc_5m')->select(DB::raw('*'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->limit(1)
                    ->first();

            if ($accumOpen) {
                $open = (float) $accumOpen->open;
            }

            /* Get Close price from 5m data and last 15 minutes */
            $accumClose = DB::table('ohlc_5m')->select(DB::raw('*'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->orderBy('ctime', 'desc')
                    ->limit(1)
                    ->first();

            if ($accumClose) {
                $close = (float) $accumClose->close;
            }

            if ($open && $close && $low && $high) {
                DB::insert("
            INSERT INTO ohlc_15m 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open, $high, $low, $close, $volume)
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
        $open   = null;
        $close  = null;
        $high   = null;
        $low    = null;
        $volume = null;

        $lastRecordedTimeId = DB::table('ohlc_30m')->select(DB::raw('MAX(timeid) AS timeid'))
                ->where('product_id', $product_id)
                ->first();

        if ($lastRecordedTimeId) {
            $lasttimeid = (int) $lastRecordedTimeId->timeid + 29 * 60;
        } else {
            $lasttimeid = 0;
        }

        if ($lasttimeid < $timeid) {
            /* Get High and Low from 15m data for insertion */
            $starttimeid = \Carbon\Carbon::now()->subMinute(30)->format('YmdHi'); // date("YmdHi", strtotime("-30 minutes", strtotime("now")));

            $accumOpenHighVolume = DB::table('ohlc_15m')->select(DB::raw('MAX(high) as high, MIN(low) as low, AVG(volume) as volume'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->first();

            if ($accumOpenHighVolume) {
                $high   = (float) $accumOpenHighVolume->high;
                $low    = (float) $accumOpenHighVolume->low;
                $volume = (int) round($accumOpenHighVolume->volume);
            }

            /* Get Open price from 15m data and last 30 minutes */
            $accumOpen = DB::table('ohlc_15m')->select(DB::raw('*'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->limit(1)
                    ->first();

            if ($accumOpen) {
                $open = (float) $accumOpen->open;
            }

            /* Get Close price from 15m data and last 30 minutes */
            $accumClose = DB::table('ohlc_15m')->select(DB::raw('*'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->orderBy('ctime', 'desc')
                    ->limit(1)
                    ->first();

            if ($accumClose) {
                $close = (float) $accumClose->close;
            }

            if ($open && $close && $low && $high) {
                DB::insert("INSERT INTO ohlc_30m 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open, $high, $low, $close, $volume)
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
        $open   = null;
        $close  = null;
        $high   = null;
        $low    = null;
        $volume = null;

        $lastRecordedTimeId = DB::table('ohlc_1h')->select(DB::raw('MAX(timeid) AS timeid'))
                ->where('product_id', $product_id)
                ->first();

        if ($lastRecordedTimeId && $lastRecordedTimeId->timeid) {
            $lasttimeid = (int) \Carbon\Carbon::createFromFormat('YmdHi', $lastRecordedTimeId->timeid)->addMinute(59)->format('YmdHi');
        } else {
            $lasttimeid = 0;
        }

        if ($lasttimeid < $timeid) {
            /* Get High and Low from 30m data for insertion */
            $starttimeid = \Carbon\Carbon::now()->subMinute(60)->format('YmdHi'); // date("YmdHi", strtotime("-60 minutes", strtotime("now")));

            $accumHighLowVolume = DB::table('ohlc_30m')->select(DB::raw('MAX(high) as high, MIN(low) as low, AVG(volume) as volume'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->first();

            if ($accumHighLowVolume) {
                $high   = (float) $accumHighLowVolume->high;
                $low    = (float) $accumHighLowVolume->low;
                $volume = $accumHighLowVolume->volume;
            }

            /* Get Open price from 30m data and last 60 minutes */
            $accumOpen = DB::table('ohlc_30m')->select(DB::raw('*'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->limit(1)
                    ->first();

            if ($accumOpen) {
                $open = (float) $accumOpen->open;
            }

            /* Get Close price from 30m data and last 60 minutes */
            $accumClose = DB::table('ohlc_30m')->select(DB::raw('*'))
                    ->where('product_id', $product_id)
                    ->where('timeid', '>=', $starttimeid)
                    ->where('timeid', '<=', ($timeid))
                    ->orderBy('ctime', 'desc')
                    ->limit(1)
                    ->first();

            if ($accumClose) {
                $close = (float) $accumClose->close;
            }

            if ($open && $close && $low && $high) {
                DB::insert("
            INSERT INTO ohlc_1h 
            (`product_id`, `timeid`, `open`, `high`, `low`, `close`, `volume`)
            VALUES
            ('$product_id', $timeid, $open, $high, $low, $close, $volume)
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
     * @param \Illuminate\Support\Collection $datas
     *
     * @return array
     */
    public function transformPairData(\Illuminate\Support\Collection $datas): array
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
                    $variance = (int) 119;
                } else {
                    if ($periodSize == '5m') {
                        $variance = (int) 375;
                    } else {
                        if ($periodSize == '15m') {
                            $variance = (int) 1125;
                        } else {
                            if ($periodSize == '30m') {
                                $variance = (int) 2250;
                            } else {
                                if ($periodSize == '1h') {
                                    $variance = (int) 4500;
                                } else {
                                    if ($periodSize == '1d') {
                                        $variance = (int) 108000;
                                    }
                                }
                            }
                        }
                    }
                }

                $periodcheck = $starttime - $endtime;

                if ((int) $periodcheck > (int) $variance) {
                    dump($starttime, $endtime, $periodcheck, $oldrow, $row);
                    echo "** YOU HAVE " . $validperiods . " PERIODS OF VALID PRICE DATA OUT OF ' . $limit . '. Please ensure price sync is running and wait for additional data to be logged before trying again. Additionally you could use a smaller time period if available.\n";
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
