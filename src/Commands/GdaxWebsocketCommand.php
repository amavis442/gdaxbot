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

/**
 * Description of GdaxWebsocketCommand
 *
 * @author patrick
 */
class GdaxWebsocketCommand extends Command {

    protected $product_id;

    protected function configure() {
        $this->setName('bot:websocket')
                ->setDescription('Get data from websocket')
                ->setHelp('Get data from websocket.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $loop = \React\EventLoop\Factory::create();
        $connector = new \Ratchet\Client\Connector($loop);

        $this->product_id = 'BTC-EUR';

        $connector('wss://ws-feed.gdax.com')
                ->then(function(\Ratchet\Client\WebSocket $conn) {
                    $conn->send('{"type": "subscribe","product_id": "' . $this->product_id . '"}');

                    $conn->on('message', function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($conn) {
                        /**
                         *   DO ALL PROCESSING HERE
                         *   match up sequence and keep the book up to date.
                         */
                        /* if (empty($this->book)) {
                            $this->book = $this->getBook($this->product_id);
                            $this->processBook();
                        } */
                        $data = json_decode($msg, 1);
                        echo print_r($data);
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
