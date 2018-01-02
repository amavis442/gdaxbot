<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use App\Traits\CandleMap;
use App\Traits\Pivots;
use App\Traits\OHLC;
use App\Util\Candles;
use App\Util\Console;
use App\Util\Indicators;
use App\Util\Cache;

/**
 * Description of TestCandlesCommand
 *
 * @author patrick
 */
class TestCandlesCommand extends Command {

    use OHLC,
        Pivots,
        CandleMap;

    protected $candles;
    protected $indicators;

    public function __construct(string $name = null) {
        parent::__construct($name);
        $this->candles = new Candles();
    }


    protected function configure() {
        $this->setName('bot:test:candles')

                // the short description shown while running "php bin/console list"
                ->setDescription('Test the candles.')
                ->setHelp('Test the candles.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->indicators = $ind = new Indicators();
        $instrument = 'BTC-EUR';
        $data       = $this->getRecentData($instrument, 70);

        $all                  = [];
        while (1) {
            $candles              = [];
            $data                 = $this->getRecentData($instrument, 70);
            $cand                 = $this->candles->allCandles($instrument, $data);
            $candles[$instrument] = $cand['current'] ?? [];
            
            $all                  = array_merge($all, $cand['current'] ?? []);

            foreach ($all as $allof => $val) {
                $candles[$instrument][$allof] = $candles[$instrument][$allof] ?? 0;
            }

            dump($candles);
            sleep(5);
        }
    }

}
