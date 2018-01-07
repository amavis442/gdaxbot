<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use App\Traits\OHLC;
use App\Util\Indicators;
use App\Util\Console;
use App\Util\Cache;

class TestTrendsCommand extends Command {

    use OHLC;

    protected $cache;
    protected $indicators;

    public function setCache($cache) {
        $this->cache = $cache;
    }

    protected function configure() {
        $this->setName('test:trends')

                // the short description shown while running "php bin/console list"
                ->setDescription('Test the trends.')
                ->setHelp('Test the trends.')
        ;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function execute(InputInterface $input, OutputInterface $output) {
        $this->indicators = $ind = new Indicators();
        $instrument = 'BTC-EUR';

        while (1) {
            $all = ['httc', 'htl', 'hts', 'mmi'];
            $data = $this->getRecentData($instrument, 200);


            $rows = [];
            switch ($ind->httc($instrument, $data)) {     # Hilbert Transform - Trend vs Cycle Mode
                case 0:
                    $rows[] = ['httc', 'Cycling mode'];
                    break;
                case 1:
                    $rows[] = ['httc', 'Trending mode'];
                    break;
            }
            switch ($ind->htl($instrument, $data)) {     # Hilbert Transform - Trend vs Cycle Mode
                case -1:
                    $rows[] = ['htl', 'Downtrend'];
                    break;
                case 0:
                    $rows[] = ['htl', '-'];
                    break;
                case 1:
                    $rows[] = ['htl', 'Uptrend'];
                    break;
            }
            switch ($ind->hts($instrument, $data)) {
                case -1:
                    $rows[] = ['hts', 'sell'];
                    break;
                case 0:
                    $rows[] = ['hts', '-'];
                    break;
                case 1:
                    $rows[] = ['hts', 'buy'];
                    break;
            }


            switch ($ind->mmi($instrument, $data)) {     # Hilbert Transform - Trend vs Cycle Mode
                case -1:
                    $rows[] = ['mmi', 'Not trending'];
                    break;
                case 0:
                    $rows[] = ['mmi', '-'];
                    break;
                case 1:
                    $rows[] = ['mmi', 'trending'];
                    break;
            }
 
            $table = new Table($output);
            $table
                    ->setHeaders(['', ''])
                    ->setRows($rows);
            $table->render();

            sleep(5);
        }
    }

}
