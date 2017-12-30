<?php


namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;


class ReportProfitsCommand extends Command {
        protected $conn;

    public function setConn($conn) {
        $this->conn = $conn;
    }
    
     protected function configure() {
        $this->setName('bot:report:profits')

                // the short description shown while running "php bin/console list"
                ->setDescription('Shows a table of profit/losses by the bot.')
                ->setHelp('Show profits/losses')
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        $orderService = new \App\Services\OrderService($this->conn);
    
        
        $rows = $orderService->getProfits();
       
        $table = new Table($output);
        $table
            ->setHeaders(['Side','Size','Amount', 'Side', 'Size','Amount', 'Profit'])
            ->setRows($rows);
        $table->render();

     }

    
}
