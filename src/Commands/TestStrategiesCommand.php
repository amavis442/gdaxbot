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
class TestStrategiesCommand extends Command
{

    use Signals,
        OHLC; // add our traits

    protected $indicators;

    protected function configure()
    {
        $this->setName('test:strategies')
                // the short description shown while running "php bin/console list"
                ->setDescription('Testing out the strategies we have.')
                ->setHelp('Testing out the strategies we have.');
    }

        /** -------------------------------------------------------------------
     * @return null
     *
     *  this is the part of the command that executes.
     *  -------------------------------------------------------------------
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $signal = new \App\Strategies\TrendingLinesStrategy();
        
        switch($signal->getSignal()) {
            case -1:
                $advise = 'Sell';
                break;
            case 0:
               $advise = 'Hold';
                break; 
            case 1:
               $advise = 'Buy';
                break; 
        }
        
        $oldadvise = Cache::get('strategy.trendslines.advise');
        
        if ($oldadvise <> $advise) {
            $output->writeln('<info>New Advise: *** '.$advise. ' ** </info>');
            Cache::put('strategy.trendslines.advise', $advise);
        }
        
        $output->writeln('<info>Old Advise: *** '.$advise. ' ** </info>');
    }
}
