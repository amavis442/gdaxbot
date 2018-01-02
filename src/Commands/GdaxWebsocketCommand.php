<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use App\Util\Cache;
use App\Traits\OHLC;

/**
 * Description of GdaxWebsocketCommand
 *
 * @author patrick
 */
class GdaxWebsocketCommand extends Command
{

    use OHLC;

    protected $product_id;
    protected $cache;
    protected $conn;

    protected function configure()
    {
        $this->setName('bot:websocket')
             ->setDescription('Get data from websocket')
             ->setHelp('Get data from websocket.');
    }

    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    public function setConn($conn)
    {
        $this->conn = $conn;
    }

    public function manageCacheArray($key, $item, $len = 60)
    {
        $storeArr      = array();
        $check_time    = time() - $len;
        $current_mtime = time();
        $value         = Cache::get($key, false);

        if ($value) {
            $value_arr = unserialize(base64_decode($value));

            foreach ($value_arr as $k => $v) {
                if (floatval($k) > floatval($check_time)) {
                    $storeArr[$k] = $v;
                }
            }
            $storeArr[$current_mtime] = $item;
            krsort($storeArr);
            $value = base64_encode(serialize($storeArr));
        } else {
            $value = array("$current_mtime" => $item);
            $value = base64_encode(serialize($value));
        }

        Cache::put($key, $value);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop      = \React\EventLoop\Factory::create();
        $connector = new \Ratchet\Client\Connector($loop);

        $this->product_id = 'BTC-EUR';


        $connector('wss://ws-feed.gdax.com')
            ->then(function (\Ratchet\Client\WebSocket $conn) {
                $subscribe = json_encode([
                                             "type"     => "subscribe",
                                             "channels" => [
                                                 [
                                                     "name"        => "ticker",
                                                     "product_ids" => [
                                                         $this->product_id
                                                     ]
                                                 ]
                                             ]
                                         ]);
                $conn->send($subscribe);

                $subscribe = json_encode([
                                             "type"     => "subscribe",
                                             "channels" => [
                                                 [
                                                     "name"        => "heartbeat",
                                                     "product_ids" => [
                                                         $this->product_id
                                                     ]
                                                 ]
                                             ]
                                         ]);

                $conn->send($subscribe);

                $conn->on('message', function (\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn) {
                    $data  = json_decode($msg, 1);
                    $cache = $this->cache;
                    if ($data['type'] == 'ticker') {
                        #echo "TICKER\n";
                        $lastPrice = Cache::get('gdax.ticker.last_price', false);

                        if ($lastPrice) {
                            $last = $lastPrice;
                        } else {
                            $last = $data['price'];
                        }

                        Cache::put('gdax.ticker.last_price', $data['price'], 180);
                        Cache::put('gdax.ticker.low', $data['low_24h'], 180);
                        Cache::put('gdax.ticker.high', $data['high_24h'], 180);
                        Cache::put('gdax.ticker.open', $data['open_24h'], 180);
                        Cache::put('gdax.ticker.bid', $data['best_bid'], 180);
                        Cache::put('gdax.ticker.ask', $data['best_ask'], 180);
                        Cache::put('gdax.ticker.volume', $data['volume_24h'], 180);

                        $this->manageCacheArray('gdax.ticker.last_price.array', $data['price']);
                        $this->manageCacheArray('gdax.ticker.volume.array', $data['volume_24h']);
                        $this->manageCacheArray('gdax.ticker.ask.array', $data['best_ask']);
                        $this->manageCacheArray('gdax.ticker.bid.array', $data['best_bid']);
                        $this->manageCacheArray('gdax.ticker.last_price_diff.array', ($data['price'] - $last));
                        //print_r($data);

                        $this->markOHLC($data);

                    }
                });

                $conn->on('close', function ($code = null, $reason = null) {
                    /** log errors here */
                    echo "Connection closed ({$code} - {$reason})\n";
                });
            }, function (\Exception $e) use ($loop) {
                /** hard error */
                echo "Could not connect: {$e->getMessage()}\n";
                $loop->stop();
            });
        $loop->run();
    }
}

