<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use App\Traits\Signals;
use App\Traits\OHLC;
use App\Strategies\Traits\Strategies;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Class ExampleCommand
 * @package Bowhead\Console\Commands
 *
 *          SEE COMMENTS AT THE BOTTOM TO SEE WHERE TO ADD YOUR OWN
 *          CONDITIONS FOR A TEST.
 *
 */
class TestStrategiesCommand extends Command {

    use Signals,
        Strategies,
        OHLC; // add our traits

    protected $cache;

    public function setCache($cache) {
        $this->cache = $cache;
    }

    protected function configure() {
        $this->setName('bot:test:strategies')

                // the short description shown while running "php bin/console list"
                ->setDescription('Testing out the strategies we have.')
                ->addOption('runtest', null, InputOption::VALUE_NONE)
                ->setHelp('Testing out the strategies we have.')
        ;
    }

    /**
     * @param $val
     *
     * @return string
     */
    public function doColor($val) {
        if ($val == 0) {
            return 'none';
        }
        if ($val == 1) {
            return 'green';
        }
        if ($val == -1) {
            return 'magenta';
        }
        return 'none';
    }

    /**
     * @param      $arr
     * @param bool $retarr
     *
     * @return array|string
     */
    public function compileSignals($arr, $retarr = false) {
        $console = new \App\Util\Console();
        $pos     = $neg     = 0;

        foreach ($arr as $a) {
            $pos += ($a > 0 ? 1 : 0);
            $neg += ($a < 0 ? 1 : 0);
        }

        if ($retarr) {
            return ['pos' => $pos, 'neg' => $neg];
        }

        return "$pos/-$neg";
    }

    /**
     *  updateDb
     */
    public function updateDb() {
        $ids = DB::table('strategy')->select(DB::raw('unix_timestamp(ctime) AS stime, position_id, pair'))->whereNull('profit')->get();

        if (!empty($ids)) {
            foreach ($ids as $id) {
                $modify = 301;
                if ($id->stime + $modify < time()) {
                    echo "\nUPDATING " . $id->position_id;
                    $order = $wc->positionGet($id->position_id);
                    $err   = $order['body']['error'] ?? null;
                    if ($err) {
                        print_r($order);
                        #\DB::table('bowhead_strategy')->where('position_id', $id->position_id)->update(['profit' => '0', 'state' => 'error', 'close_reason' => 'error']);
                        return;
                    }
                    $prof = $order['profit'] ?? null;
                    if (!is_null($prof)) {
                        DB::table('strategy')->where('position_id', $id->position_id)->update(['profit' => $prof, 'state' => $order['state'], 'close_reason' => $order['close_reason']]);
                    }
                }
            }
        }
    }

    /**
     * @param     $instrument
     * @param     $direction
     * @param     $strategy
     * @param int $pos
     * @param int $neg
     * @param int $size
     * @param int $lev
     *
     * @return array|bool
     */
    public function createPosition($instrument, $direction, $strategy, $pos = 0, $neg = 0, $size = 2, $lev = 200) {
        $cache_key = "wc.demo.$instrument.$direction.$strategy";
        $cacheItem = $this->cache->getItem($cache_key);
        if ($cacheItem->isHit()) {
            $cacheItem->expiresAfter(60);
            return false;
        }

        $gdaxService = new \App\Services\GDaxService();
        $gdaxService->setCoin(getenv('CRYPTOCOIN'));
        $gdaxService->connect();

        $price = $gdaxService->getCurrentPrice();


        /**
         *  These are just example stop loss and take profit amounts.
         *  We are just gonna let these ride and see what happens.
         *
         *  Example of EUR/GBP 200 leverage percentage:
         *   (price*(( % /leverage)/100)) = amount that is %
         *  (0.87881*(30/200))/100 = 0.00131821500000000000
         *
         *  30% of price with 200 leverage = ((30/200)/100) = 0.15%
         *
         */
        $tp             = round(( $price * (20 / $lev) ) / 100, 5);
        $sl             = round(( $price * (10 / $lev) ) / 100, 5);
        $amt_takeprofit = ($direction == 'long' ? ((float) $price + $tp) : ((float) $price - $tp));
        $amt_stoploss   = ($direction == 'long' ? ((float) $price - $sl) : ((float) $price + $sl));


        // Create sell of buy order

        /*
          print_r($info);
          $err   = $info['error']['code'] ?? null;
          if (isset($info['error']) && is_array($info['error'])) {
          return ['error' => $err];
          }
          $insert['position_id'] = $info['id'];
          $insert['pair']        = "$instrument";
          $insert['direction']   = "$direction";
          $insert['signalpos']   = $pos;
          $insert['signalneg']   = $neg;
          $insert['strategy']    = "$strategy";
          $insert['state']       = 'active';
          DB::table('bowhead_strategy')->insert($insert);
         */

        $cacheItem->set(1); // at least five minutes
        $this->cache->save($cacheItem);

        return true;
    }

    /** -------------------------------------------------------------------
     * @return null
     *
     *  this is the part of the command that executes.
     *  -------------------------------------------------------------------
     */
    public function execute(InputInterface $input, OutputInterface $output) {
        $rundemo         = false;
        $pair_strategies = $recentprices    = [];

        if ($rundemo = $input->getOption('runtest')) {
            $output->writeln("<info>Running the DEMO test of all the strategies:</info>");
        }

        $console = new \App\Util\Console();

        $instruments = ['BTC-EUR'];
        $leverages   = [1];
        /**
         *  $strategies = $this->strategies_all = every single strategy.
         *  $strategies = $this->strategies_1m  = only 1 minute periods
         *  $strategies = $this->strategies_5m  = only 5 minute periods
         *  $strategies = $this->strategies_15m = fifteen minute periods
         *  $strategies = $this->strategies_30m = thirty
         *  $strategies = $this->strategies_1h  = sixty
         */
        $strategies  = $this->strategies_1m;

        foreach ($strategies as $k => $strategy) {
            $strategies[$k] = str_replace('bowhead_', '', $strategy);
        }

        $recentprices = [];

        /**
         *  GET ALL OUR SIGNALS HERE
         */
        $signalsA = $this->signals($instruments); // get the full list
        $signals  = $signalsA['symbol'];

        /**
         *  First up we loop through the strategies dynamically run the strategies
         *  using $this->${'strategy'}(param1, param2)
         *  $pair_strategies just has [pair][strategy] = {-1/1/0}
         */
        foreach ($instruments as $instrument) {
            $recentData                = $this->getRecentData($instrument, 220);
            $recentData_copy           = $recentData['close'];
            $recentprices[$instrument] = array_pop($recentData_copy);
            $flags                     = [];

            /**
             *  Using $strategies_all from strategies trait
             */
            foreach ($strategies as $strategy) {
                $function_name    = 'bowhead_' . $strategy;
                $flags[$strategy] = $this->$function_name($instrument, $recentData);
            }
            $pair_strategies[$instrument] = $flags;
        }

        /**
         *   If we want to just view what the strategies
         *   are currently returning in a colored table.
         */
        if (!$rundemo) {
            $lines        = [];
            $lines['top'] = '';
            $output       = '';
            foreach ($instruments as $instrument) {
                $comp         = $this->compileSignals($signals[$instrument]);
                $lines['top'] .= str_pad($instrument . "[$comp]", 17);
                foreach ($strategies as $strategy) {
                    if (!isset($lines[$strategy])) {
                        $lines[$strategy] = '';
                    }
                    $color            = ($pair_strategies[$instrument][$strategy] > 0 ? 'bg_green' : ($pair_strategies[$instrument][$strategy] < 0 ? 'bg_red' : 'bg_black'));
                    $lines[$strategy] .= $console->colorize(str_pad($strategy, 17), $color);
                }
            }
            echo "\n\n" . $console->colorize(@$lines['top']);
            foreach ($strategies as $strategy) {
                echo "\n" . $lines[$strategy];
            }
        } else {
            /**
             *  DO THE ACTUAL TESTS...
             *  HERE IS WHERE WE CAN BUILD UP CUSTOM STRATEGY TESTS
             */
            foreach ($pair_strategies as $pair => $strategies) {

                $sigs = $this->compileSignals($signals[$pair], 1);

                foreach ($strategies as $strategy => $flag) {
                    if ($flag == 0) {
                        continue; // not a short or a long
                    }
                    
                    $direction = ($pair_strategies[$pair][$strategy] > 0 ? 'long' : 'short');
                    
                    /**
                     *  Here we determine the leverage based on signals.
                     *  There are only a certain leverage steps we can use
                     *  so we need to fit into the closest 222,200,100,88,50,25,1
                     */
                    $lev       = 220;
                    $closest   = 0;
                    $lev       = ($direction == 'long' ? $lev - ($sigs['neg'] * 20) : $lev - ($sigs['pos'] * 20));

                    /** now we have the leverage. */
                    /**
                     *   TODO:      HERE IS WHERE YOU CAN TEST SIGNALS BEFORE YOU CREATE
                     *   TODO:      POSITIONS AND SEND THEM OUT.
                     *   TODO:      YOU CAN REFINE YOUR STRATEGIES HERE FURTHER.
                     *   TODO:      THIS IS REALLY JUST A SIMPLE AND EASY STARTING OFF
                     *   TODO:      POINT FOR YOU.
                     */
                    /*
                     *  You could specify something like the following
                     *  if ($direction == 'long && $sigs['pos'] > 8 || $direction == 'short && $sigs['neg'] > 8) {
                     *  Which would use the signals to verify your trades.
                     */
                    // TODO ******************************************************************************************

                    $output->writeln("Create $direction for $pair $strategy");

                    //$order = $this->createPosition($pair, $direction, $strategy, $sigs['pos'], $sigs['neg'], 2, $lev);

                    // TODO ******************************************************************************************
                    /*
                     *   You may want to do any post-processing you need here with $order
                     *   Order will look like: http://docs.whaleclub.co/#new-position
                     */
                }
            }
        }
        //$this->updateDb();


        return null;
    }

}
