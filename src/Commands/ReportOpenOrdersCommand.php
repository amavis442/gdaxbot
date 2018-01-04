<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use App\Util\Transform;

/**
 * Description of ReportOpenOrders
 *
 * @author patrick
 */
class ReportOpenOrdersCommand  extends Command {
    
    protected function configure() {
        $this->setName('report:openorders')

                // the short description shown while running "php bin/console list"
                ->setDescription('Shows a table of current open orders.')
                ->setHelp('Show open/pending sell/buy orders')
        ;
    }
    
     protected function execute(InputInterface $input, OutputInterface $output) {
        $orderService = new \App\Services\OrderService();
        
        $rows = $orderService->getOpenBuyOrders();
        $rows += $orderService->getOpenSellOrders();
      
        $table = new Table($output);
        $table
            ->setHeaders(['id','parent_id','side', 'size', 'amount','status', 'order_id','strategy', 'take_profit','signalpos','signalneg','close_reason','created_at', 'updated_at'])
            ->setRows($rows);
        $table->render();

     }
}
