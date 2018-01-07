<?php

namespace App\Commands;

use App\Util\Indicators;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use App\Util\Cache;
use App\Traits\Signals;
use App\Traits\OHLC;
use App\Strategies\Traits\Strategies;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Class ExampleCommand
 *
 * @package Bowhead\Console\Commands
 *
 *          SEE COMMENTS AT THE BOTTOM TO SEE WHERE TO ADD YOUR OWN
 *          CONDITIONS FOR A TEST.
 *
 */
class TestStoplossCommand extends Command
{

    use Signals,
        OHLC; // add our traits

    protected $indicators;

    protected function configure()
    {
        $this->setName('test:stoploss')
                // the short description shown while running "php bin/console list"
                ->setDescription('Testing out the strategies we have.')
                ->addArgument('buyprice', InputArgument::REQUIRED, 'Buy price')
                ->addArgument('percentage', InputArgument::REQUIRED, 'Stoploss percentage')
                ->setHelp('Testing stoploss.');
    }

        /** -------------------------------------------------------------------
     * @return null
     *
     *  this is the part of the command that executes.
     *  -------------------------------------------------------------------
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $buyprice = $input->getArgument('buyprice');
        $percentage = $input->getArgument('percentage');
        
        $gdaxService = new \App\Services\GDaxService();
        $gdaxService->setCoin(getenv('CRYPTOCOIN'));
        $gdaxService->connect(false);
        
        $stoploss = new \App\Rules\Stoploss();

        
        while(1)
        {
            $currentprice = $gdaxService->getCurrentPrice();
            $sell = $stoploss->trailingStop($currentprice, $buyprice, $percentage, $output);
            
            sleep(5);
            
        }
        
      
    }
}
