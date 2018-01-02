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

use App\Strategies\Traits\TrendingLinesStrategy;

use App\Bot\Gdaxbot;

/**
 * Description of RunBotCommand
 *
 * @author patrick
 */
class RunBotCommand extends Command {

    protected $conn;
    protected $indicators;

    public function setConn($conn) {
        $this->conn = $conn;
    }

    protected function configure() {
        $this->setName('bot:run')
                ->setDescription('Runs the bot for 1 cycle use cron to call this command.')
                ->addOption('sandbox', null, InputOption::VALUE_NONE, 'Run bot in sandbox so no real trades will be made.')
                ->setHelp('Runs the bot for 1 cycle use cron to call this command.');
    }


    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->indicators = new Indicators();


        $settingsService = new \App\Services\SettingsService($this->conn);
        $orderService = new \App\Services\OrderService($this->conn);
        $gdaxService = new \App\Services\GDaxService(); 
        $gdaxService->setCoin(getenv('CRYPTOCOIN'));

        $sandbox = false;
        if($input->getOption('sandbox')) {
            $output->writeln('<info>Running in sandbox mode</info>');
            $sandbox = true;
        }
        
        $gdaxService->connect($sandbox);

        $app = new Gdaxbot($settingsService->getSettings(), $orderService, $gdaxService);

        $output->writeln($gdaxService->getProductId());
        $output->writeln("Ready to run");

        $app->run($this->getStrategy());
    }

}
