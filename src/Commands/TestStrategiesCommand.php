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
        Strategies,
        OHLC; // add our traits

    protected $indicators;

    protected function configure()
    {
        $this->setName('bot:test:strategies')
                // the short description shown while running "php bin/console list"
                ->setDescription('Testing out the strategies we have.')
                ->addOption('runtest', null, InputOption::VALUE_NONE)
                ->setHelp('Testing out the strategies we have.');
    }

    /**
     * @param      $arr
     * @param bool $retarr
     *
     * @return array|string
     */
    public function compileSignals($arr, $retarr = false)
    {
        $pos = $neg = 0;

        foreach ($arr as $a) {
            $pos += ($a > 0 ? 1 : 0);
            $neg += ($a < 0 ? 1 : 0);
        }

        if ($retarr) {
            return ['pos' => $pos, 'neg' => $neg];
        }

        return "$pos/-$neg";
    }

    /** -------------------------------------------------------------------
     * @return null
     *
     *  this is the part of the command that executes.
     *  -------------------------------------------------------------------
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $pair_strategies  = $recentprices     = [];
        $this->indicators = new Indicators();
        $instrument       = 'BTC-EUR';

        /**
         *  $strategies = $this->strategies_all = every single strategy.
         *  $strategies = $this->strategies_1m  = only 1 minute periods
         *  $strategies = $this->strategies_5m  = only 5 minute periods
         *  $strategies = $this->strategies_15m = fifteen minute periods
         *  $strategies = $this->strategies_30m = thirty
         *  $strategies = $this->strategies_1h  = sixty
         */
        $strategies = $this->strategies_1m;

        foreach ($strategies as $k => $strategy) {
            $strategies[$k] = str_replace('bowhead_', '', $strategy);
        }

        $recentprices = [];

        /**
         *  GET ALL OUR SIGNALS HERE
         */
        $recentData = $this->getRecentData($instrument, 220);

        $signalsA = $this->signals($recentData); // get the full list
        $signals  = $signalsA['flags'];

        /**
         *  First up we loop through the strategies dynamically run the strategies
         *  using $this->${'strategy'}(param1, param2)
         *  $pair_strategies just has [pair][strategy] = {-1/1/0}
         */
        $recentData_copy           = $recentData['close'];
        $recentprices[$instrument] = array_pop($recentData_copy);
        $flags                     = [];

        /**
         *  Using $strategies_all from strategies trait
         */
        foreach ($strategies as $strategy) {
            $function_name    = 'bowhead_' . $strategy;
            $flags[$strategy] = $this->$function_name($recentData);
        }




        foreach ($flags as $strategy => $result) {
            $sigs = $this->compileSignals($signals, 1);
            $output->writeln($strategy. ':'.$result);

            if ($result == 0) {
                continue; // not a short or a long
            }

            $direction = ($result > 0 ? 'long' : 'short');

            /**
             *  Here we determine the leverage based on signals.
             *  There are only a certain leverage steps we can use
             *  so we need to fit into the closest 222,200,100,88,50,25,1
             */
            $lev = 220;
            $lev = ($direction == 'long' ? $lev - ($sigs['neg'] * 20) : $lev - ($sigs['pos'] * 20));

            $output->writeln("Create $direction for $strategy. Lev $lev");
        }
        $output->writeln("Done");

        return null;
    }

}
