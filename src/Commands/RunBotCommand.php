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

use App\Traits\ActualizeBuysAndSells;
use App\Strategies\Traits\TrendingLinesStrategy;


/**
 * Description of RunBotCommand
 *
 * @author patrick
 */
class RunBotCommand extends Command {
    use TrendingLinesStrategy, ActualizeBuysAndSells;

    protected $conn;
    protected $indicators;
    protected $gdaxService;
    protected $orderService;
    protected $settingsService;
    protected $spread;
    protected $sellspread;
    protected $order_size;
    protected $max_orders_per_run;
    protected $waitingtime;
    protected $lifetime;
    protected $pendingBuyPrices;
    protected $bottomBuyingTreshold;
    protected $topBuyingTreshold;

    protected $outputConsole;

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
        $this->outputConsole = $output;

        $this->indicators = new Indicators();

        $this->settingsService = new \App\Services\SettingsService($this->conn);
        $this->orderService = new \App\Services\OrderService($this->conn);

        $this->gdaxService = new \App\Services\GDaxService();
        $this->gdaxService->setCoin(getenv('CRYPTOCOIN'));

        // Settings
        $this->max_orders_per_run = getenv('MAX_ORDERS_PER_RUN');
        $this->waitingtime = getenv('WAITINGTIME');

        $settings = $this->settingsService->getSettings();
        $this->spread = $settings['spread'];
        $this->sellspread = $settings['sellspread'];
        $this->order_size = $settings['size'];
        $this->max_orders = $settings['max_orders'];

        $this->lifetime = $settings['lifetime'];

        $this->bottomBuyingTreshold = $settings['bottom'];
        $this->topBuyingTreshold = $settings['top'];

        $botactive = ($settings['botactive'] == 1 ? true : false);


        $sandbox = false;
        if($input->getOption('sandbox')) {
            $output->writeln('<info>Running in sandbox mode</info>');
            $sandbox = true;
        }

        if ($botactive) {
            $this->gdaxService->connect($sandbox);

            $output->writeln("Delete orders without order id");
            $this->orderService->garbageCollection();

            $output->writeln("Check gdax exchange with database for orders in gdax but not in database");
            $this->actualize();

            $output->writeln("Check gdax if sells have changed status from open to filled");
            $this->actualizeSells();

            $output->writeln("Fix rejected sells so we can sell them");
            $this->orderService->fixRejectedSells();

            $output->writeln("** Place sell orders");
            $this->sell();

            $output->writeln("Check gdax if buys have changed status from open to filled");
            $this->actualizeBuys();

            $output->writeln("A buy order has x seconds to complete before removed and new buy is placed");
            $this->timeoutBuyOrders();

            $output->writeln("** Place buy orders");
            $this->buy($this->getStrategy());


            $output->writeln("=== DONE " . date('Y-m-d H:i:s')." ===");
        } else {
            $output->writeln("<info>Bot is not active at the moment</info>");
        }
    }

}
