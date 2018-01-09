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
use App\Contracts\IndicatorInterface;

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

    use OHLC; // add our traits

    protected $container;

    public function setContainer($container)
    {
        $this->container = $container;
    }

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
        $strategyManager = $this->container->get('manager.strategy');

       
        while (1) {
            $data = $this->getRecentData();

            $result = $strategyManager->trendlines($data, $this->container->get('manager.indicators'));

            $output->write(\Carbon\Carbon::now('Europe/Amsterdam')->format('Y-m-d H:i:s'). ' .... ');
            
            switch ($result) {
                case IndicatorInterface::SELL:
                    $output->writeln('<error>Sell</error>');
                    break;
                case IndicatorInterface::HOLD:
                    $output->writeln('<info>Sell</info>');
                    break;
                case IndicatorInterface::BUY:
                    $output->writeln('<comment>Buy</comment>');
                    break;
            }

            sleep(5);
        }

        $output->writeln("Exit ticker");
    }
}
