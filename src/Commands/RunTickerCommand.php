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

    protected function configure()
    {
        $this->setName('ticker:run')
             ->setDescription('Runs the ticker.')
             ->setHelp('Runs the ticker.');
    }

    protected function updateTicker($pair = 'BTC-EUR')
    {
        // Ticker
        $res = $this->httpClient->request('GET', 'https://api.gdax.com/products/' . $pair . '/ticker');

        if ($res->getStatusCode() == 200) {
            $jsonData = $res->getBody();
            $data     = json_decode($jsonData, true);

            $ticker               = [];
            $ticker['product_id'] = $pair;
            $ticker['timeid']     = (int)\Carbon\Carbon::parse($data['time'])->setTimezone('Europe/Amsterdam')->format('YmdHis');
            $ticker['volume']     = (int)round($data['volume']);
            $ticker['price']      = (float)number_format($data['price'], 2, '.', '');

            $this->markOHLC($ticker);
        }
    }


    protected function init()
    {
        $this->settingsService = new \App\Services\SettingsService();
        $this->orderService    = new \App\Services\OrderService();
        $this->httpClient = new \GuzzleHttp\Client();
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
