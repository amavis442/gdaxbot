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


/**
 * Description of RunTicker
 *
 * @author patrickteunissen
 */
class RunTickerCommand extends Command
{
   protected $container;

    protected function configure()
    {
        $this->setName('bot:run:ticker')
             ->setDescription('Runs the ticker.')
             ->setHelp('Runs the ticker.');
    }

    public function setContainer($container)
    {
        $this->container = $container;
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("=== RUN [" . \Carbon\Carbon::now('Europe/Amsterdam')->format('Y-m-d H:i:s') . "] ===");
        $settings = $this->container->get('bot.settings');
        $config = $settings->getSettings();

        $bot = new \App\Bot\TickerBot();
        $bot->setContainer($this->container);
        $bot->setSettings($config);

        while (1) {
            $bot->run();
            $msgs= $bot->getMessage();
            foreach ($msgs as $msg){
                $output->writeln($msg);
            }

            sleep(5);
        }
        
        $output->writeln("Exit ticker");
    }
}
