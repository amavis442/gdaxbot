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

use Illuminate\Database\Query\Builder;
use App\Traits\OHLC;

/**
 * Description of GdaxWebsocketCommand
 *
 * @author patrick
 */
class GdaxWebsocketCommand extends Command {
    
    use OHLC;
    
    protected $product_id;
    protected $cache;
    protected $conn;

    protected function configure() {
        $this->setName('bot:websocket')
                ->setDescription('Get data from websocket')
                ->setHelp('Get data from websocket.');
    }

    public function setCache($cache) {
        $this->cache = $cache;
    }

    public function setConn($conn) {
        $this->conn = $conn;
    }

    public function manageCacheArray($key, $item, $len = 60) {
        $storeArr = array();
        $check_time = time() - $len;
        $current_mtime = time();
        $cacheItem = $this->cache->getItem($key);
        
        

        if ($cacheItem->isHit()) {
            $value = $cacheItem->get();
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

        $cacheItem->set($value);
        $this->cache->save($cacheItem);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $loop = \React\EventLoop\Factory::create();
        $connector = new \Ratchet\Client\Connector($loop);

        $this->product_id = 'BTC-EUR';


        $connector('wss://ws-feed.gdax.com')
                ->then(function(\Ratchet\Client\WebSocket $conn) {
                    $subscribe = json_encode(["type" => "subscribe",
                        "channels" => [
                            [
                                "name" => "ticker",
                                "product_ids" => [
                                    $this->product_id
                                ]
                            ]
                        ]
                    ]);
                    $conn->send($subscribe);

                    $subscribe = json_encode(["type" => "subscribe",
                        "channels" => [
                            [
                                "name" => "heartbeat",
                                "product_ids" => [
                                    $this->product_id
                                ]
                            ]
                        ]
                    ]);

                    $conn->send($subscribe);

                    $conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn) {
                        $data = json_decode($msg, 1);
                        $cache = $this->cache;
                        if ($data['type'] == 'ticker') {
                            #echo "TICKER\n";
                            $lastPrice = $cache->getItem('gdax.ticker.last_price');

                            if ($lastPrice->isHit()) {
                                $last = $lastPrice->get('gdax.ticker.last_price');
                            } else {
                                $last = $data['price'];
                            }

                            $lastPrice->set($data['price']);
                            $lastPrice->expiresAfter(180);
                            $cache->save($lastPrice);

                            $low = $cache->getItem('gdax.ticker.low');
                            $low->expiresAfter(180);
                            $low->set($data['low_24h']);
                            $cache->save($low);

                            $high = $cache->getItem('gdax.ticker.high');
                            $high->expiresAfter(180);
                            $low->set($data['high_24h']);
                            $cache->save($high);

                            $open = $cache->getItem('gdax.ticker.open');
                            $open->expiresAfter(180);
                            $open->set($data['open_24h']);
                            $cache->save($open);

                            $bid = $cache->getItem('gdax.ticker.bid');
                            $bid->expiresAfter(180);
                            $bid->set($data['best_bid']);
                            $cache->save($bid);

                            $ask = $cache->getItem('gdax.ticker.ask');
                            $ask->expiresAfter(180);
                            $ask->set($data['best_ask']);
                            $cache->save($ask);

                            $volume = $cache->getItem('gdax.ticker.volume');
                            $volume->expiresAfter(180);
                            $volume->set($data['volume_24h']);
                            $cache->save($volume);

                            $this->manageCacheArray('gdax.ticker.last_price.array', $data['price']);
                            $this->manageCacheArray('gdax.ticker.volume.array', $data['volume_24h']);
                            $this->manageCacheArray('gdax.ticker.ask.array', $data['best_ask']);
                            $this->manageCacheArray('gdax.ticker.bid.array', $data['best_bid']);
                            $this->manageCacheArray('gdax.ticker.last_price_diff.array', ($data['price'] - $last));
                            //print_r($data);
                            
                            $this->markOHLC($data);
                            
                        }
                    });

                    $conn->on('close', function($code = null, $reason = null) {
                        /** log errors here */
                        echo "Connection closed ({$code} - {$reason})\n";
                    });
                }, function(\Exception $e) use ($loop) {
                    /** hard error */
                    echo "Could not connect: {$e->getMessage()}\n";
                    $loop->stop();
                });
        $loop->run();
    }

    public function displayPage($message) {
        if ($message['type'] == 'match') {
            print_r($message);
        }
        $cols = getenv('COLUMNS');
        $rows = getenv('LINES');
        #print_r($message);
    }

    /**
     * @return mixed
     */
    private function getBook($instrument) {
        $util = new Util\Coinbase();
        return $util->get_endpoint('book', null, '?level=2', $instrument);
    }

    /**
     *  reformat $this->book
     */
    private function processBook() {
        $_bids = array_reverse($this->book['bids']);
        $_asks = array_reverse($this->book['asks']);
        foreach ($_bids as $bid) {
            $bids[$bid[0]] = ($bid[1] * $bid[2]); #array($bid[1], $bid[2], 0);
        }
        foreach ($_asks as $ask) {
            $asks[$ask[0]] = ($ask[1] * $ask[2]); #array($ask[1], $ask[2], 0);
        }
        $this->book = array('sell' => $asks, 'buy' => $bids);
        #print_r($this->book);
        #die();
    }

    public function displayBook($modify = null) {
        $cols = getenv('COLUMNS');
        $rows = getenv('LINES');
        $halfway = round(($rows / 2) - 1);
        if (!empty($modify)) {
            if ($modify['type'] == 'received' || $modify['type'] == 'match') {
                return true;
            }
            if ($modify['type'] == 'open') {
                if ($modify['side'] == 'sell') {
                    $this->book['sell'][$modify['price']] = array(@$modify['remaining_size'], 1, 1);
                } else {
                    $this->book['buy'][$modify['price']] = array(@$modify['remaining_size'], 1, 1);
                }
            } elseif ($modify['type'] == 'done') {
                if ($modify['side'] == 'sell') {
                    unset($this->book['sell'][@$modify['price']]);
                } else {
                    unset($this->book['buy'][@$modify['price']]);
                }
            }
        }
        foreach ($this->book['sell'] as $key => $sell) {
            $line = str_pad(money_format('%.2n', $key), 10, ' ', STR_PAD_LEFT) . str_pad($sell[0], 15, ' ', STR_PAD_LEFT);
            $color = ($sell[2] == 1 ? 'bg_light_red' : 'light_red');
            $lines[$key] = $this->console->colorize($line, $color) . "\n";
            #$this->book['sell']["$key"][2] = 0;
        }
        krsort($lines);
        $sells = array_slice($lines, -$halfway);
        foreach ($sells as $sell) {
            echo $sell;
        }
        echo "----------|-----------------\n";
        $lines = array();
        foreach ($this->book['buy'] as $key => $buy) {
            $line = str_pad(money_format('%.2n', $key), 10, ' ', STR_PAD_LEFT) . str_pad($buy[0], 15, ' ', STR_PAD_LEFT);
            $bcolor = ($buy[2] == 1 ? 'bg_light_green' : 'light_green');
            $lines[$key] = $this->console->colorize($line, $bcolor) . "\n";
            #$this->book['buy'][$key][2] = 0;
        }
        ksort($lines);
        $buys = array_slice($lines, -$halfway);
        foreach ($buys as $buy) {
            echo $buy;
        }
        return true;
    }

}
