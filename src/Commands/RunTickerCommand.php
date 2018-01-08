<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace App\Commands;


use App\Util\Indicators;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use App\Util\Cache;
use App\Traits\OHLC;
use App\Util\PositionConstants;


/**
 * Description of RunTicker
 *
 * @author patrickteunissen
 */
class RunTickerCommand extends Command
{
    use OHLC;

    /**
     * @var \GuzzleHttp\Client();
     */
    protected $httpClient;
    
    /**
     * @var \App\Contracts\OrderServiceInterface;
     */
    protected $orderService;
    /**
     * @var \App\Contracts\StrategyInterface;
     */
    protected $settingsService;

    protected $container;

    protected function configure()
    {
        $this->setName('bot:run:ticker')
             ->setDescription('Runs the ticker.')
             ->setHelp('Runs the ticker.');
    }

    public function setContainer($container)
    {
        $this->container = $container;
    }

    protected function init()
    {
        $this->settingsService = $this->container->get('bot.settings');
        $this->orderService = $this->container->get('bot.service.order');
        $this->gdaxService = $this->container->get('bot.service.gdax');
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
            $time = $tickerData->getTime();
            $timeStr = $time->format('Y-m-d H:i:s');
            $ticker['timeid']     = (int)\Carbon\Carbon::parse($timeStr)->setTimezone('Europe/Amsterdam')->format('YmdHis');
            $ticker['volume']     = (int)round($tickerData->getVolume());
            $ticker['price']      = (float)number_format($tickerData->getPrice(), 2, '.', '');

            $this->markOHLC($ticker);
        }
    }





    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init();
        $output->writeln("=== RUN [" . \Carbon\Carbon::now('Europe/Amsterdam')->format('Y-m-d H:i:s') . "] ===");

        while (1) {
            $this->updateTicker();
            sleep(5);
        }
        
        $output->writeln("Exit ticker");
    }
}
