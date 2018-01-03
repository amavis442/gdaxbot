<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use App\Util\Indicators;
use App\Traits\Signals;
use App\Traits\OHLC;
use App\Util\Cache;

/**
 * Class ExampleCommand
 * @package Bowhead\Console\Commands
 */
class TestSignalsCommand extends Command
{

    use Signals,
        OHLC;

    protected $indicators = null;

    protected function configure()
    {
        $this->setName('bot:test:signals')

                // the short description shown while running "php bin/console list"
                ->setDescription('Test the signals.')
                ->setHelp('Test the signals.');
    }

    /**
     * @return null
     *
     *  this is the part of the command that executes.
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->indicators = new Indicators();

        while (1) {
         
            $data = $this->getRecentData('BTC-EUR');
            $signalData  = $this->signals($data);

            $s = ['flags', 'ret'];
            foreach ($s as $nametype) {
                $data = $signalData[$nametype];

                $table = new Table($output);
                $rows  = [];
                foreach ($data as $name => $value) {
                    $rows[] = [$name, $value];
                }

                $table
                        ->setHeaders(['', ''])
                        ->setRows($rows);
                $table->render();
            }
            $output->writeln('Signal strength for buy/sell: ' . $signalData['strength']);
            sleep(5);
        }


        return null;
    }

}
