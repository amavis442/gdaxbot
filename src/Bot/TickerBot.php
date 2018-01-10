<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 09-01-18
 * Time: 10:41
 */

namespace App\Bot;

use App\Contracts\BotInterface;
use App\Traits\OHLC;

class TickerBot implements BotInterface
{
    use OHLC;

    protected $container;
    protected $config;
    protected $tsettingsService;
    protected $orderService;
    protected $gdaxService;
    protected $msg = [];

    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function setSettings(array $config = [])
    {
        $this->config = $config;
    }

    public function getMessage(): array
    {
        return $this->msg;
    }

    protected function init()
    {
        $this->orderService = $this->container->get('bot.service.order');
        $this->gdaxService  = $this->container->get('bot.service.gdax');
    }

    protected function updateTicker($pair = 'BTC-EUR')
    {

        $product = (new \GDAX\Types\Request\Market\Product())->setProductId($pair);
        /** @var \GDAX\Types\Response\Market\ProductTicker $tickerData */
        $tickerData = $this->gdaxService->getClient()->getProductTicker($product);


        if ($tickerData instanceof \GDAX\Types\Response\Market\ProductTicker) {
            $ticker               = [];
            $ticker['product_id'] = $pair;
            /** @var \DateTime $time */
            $time             = $tickerData->getTime(); // UTC
            $d                = \Carbon\Carbon::instance($time);
            $ticker['timeid'] = (int)$d->setTimezone('Europe/Amsterdam')->format('YmdHis');
            $ticker['volume'] = (int)round($tickerData->getVolume());
            $ticker['price']  = (float)number_format($tickerData->getPrice(), 2, '.', '');

            $this->markOHLC($ticker);
        }
    }

    public function run(): array
    {
        $this->init();
        $this->updateTicker();

        return [];
    }
}