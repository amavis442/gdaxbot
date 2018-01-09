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
use App\Traits\Positions;
use App\Traits\ActualizeBuysAndSells;
use App\Strategies\Traits\TrendingLinesStrategy;
use App\Util\Cache;
use App\Traits\OHLC;
use App\Util\PositionConstants;

/**
 * Description of RunBotCommand
 *
 * @author patrick
 */
class RunUpdatePositionsCommand extends Command
{
    protected $container;


    public function setContainer($container)
    {
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setName('bot:run:positions')
            ->setDescription('Update the positions.')
            ->addOption('test', null, InputOption::VALUE_NONE, 'Run bot, but is will not open an/or close positions but it will update the database so please use a _dev database.')
            ->setHelp('Runs the bot for 1 cycle use cron to call this command.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $settings = $this->container->get('bot.settings');
        $config = $settings->getSettings();

        $bot = new \App\Bot\PositionBot();
        $bot->setContainer($this->container);
        $bot->setSettings($config);

        while (1) {
            $bot->run();
            $msgs = $bot->getMessage();
            foreach ($msgs as $msg){
                $output->writeln($msg);
            }

            sleep(5);
        }
    }
}
