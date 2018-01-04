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

use App\Bot\Gdaxbot;
use Symfony\Component\Console\Helper\Table;

/**
 * Description of ReportCommand
 *
 * @author patrick
 */
class ReportCommand extends Command {
    
    protected function configure() {
        $this->setName('report')

                // the short description shown while running "php bin/console list"
                ->setDescription('Report of the wallet.')
                ->setHelp('Gets the balance, current price and value of the account')
        ;
    }
    
     protected function execute(InputInterface $input, OutputInterface $output) {
        $gdaxService = new \App\Services\GDaxService(); 
        $gdaxService->setCoin(getenv('CRYPTOCOIN'));
        $gdaxService->connect();
        
    
        $data = $gdaxService->getAccountReport(getenv('CRYPTOCOIN'));
          
        $rows[] = [getenv('CRYPTOCOIN'), $data['balance'], $data['koers'], $data['waarde']];
        
        
        $table = new Table($output);
        $table
            ->setHeaders(['Name', 'Balance', 'Current price','Value'])
            ->setRows($rows);
        $table->render();

     }
}
