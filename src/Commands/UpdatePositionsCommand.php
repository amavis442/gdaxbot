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
class UpdatePositionsCommand extends Command
{

    use ActualizeBuysAndSells,
        OHLC,
        Positions;

    protected $testMode = false;

    /**
     * @var \App\Contracts\GdaxServiceInterface
     */
    protected $gdaxService;

    /**
     * @var \App\Contracts\OrderServiceInterface;
     */
    protected $orderService;

    /**
     * @var \App\Contracts\StrategyInterface;
     */
    protected $settingsService;
    protected $httpClient;
    protected $container;
    protected $positionService;
    protected $stoplossRule;

    public function setContainer($container)
    {
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setName('bot:run:update')
            ->setDescription('Update the positions.')
            ->addOption('test', null, InputOption::VALUE_NONE, 'Run bot, but is will not open an/or close positions but it will update the database so please use a _dev database.')
            ->addOption('sandbox', null, InputOption::VALUE_NONE, 'Run bot in sandbox so no real trades will be made.')
            ->setHelp('Runs the bot for 1 cycle use cron to call this command.');
    }

    protected function init($sandbox = false)
    {
        $this->settingsService = new \App\Services\SettingsService();
        $this->orderService = new \App\Services\OrderService();
        $this->gdaxService = new \App\Services\GDaxService();
        $this->httpClient = new \GuzzleHttp\Client();
        $this->positionService = new \App\Services\PositionService();
        $this->stoplossRule = new \App\Rules\Stoploss();

        $this->gdaxService->setCoin(getenv('CRYPTOCOIN'));
        $this->gdaxService->connect($sandbox);
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sandbox = false;
        if ($input->getOption('sandbox')) {
            $output->writeln('<info>Running in sandbox mode</info>');
            $sandbox = true;
        }

        if ($input->getOption('sandbox')) {
            $this->testMode = true;
        }

        $this->init($sandbox);

        // Get Account
        $account = $this->gdaxService->getAccount('EUR');

        // Now we can use strategy
        /** @var \App\Contracts\StrategyInterface $strategy */
        $strategy = $this->getStrategy();
        $buyRule = $this->getRule('buy');

        while (1) {
            $output->writeln("=== RUN [" . \Carbon\Carbon::now('Europe/Amsterdam')->format('Y-m-d H:i:s') . "] ===");
            // Settings
            $config = [];
            $config['max_orders_per_run'] = getenv('MAX_ORDERS_PER_RUN');
            $config = array_merge($config, $this->settingsService->getSettings());
            $spread = $config['spread'];

            //Cleanup
            $this->orderService->garbageCollection();
            $this->actualize();
            $this->actualizeBuys();
            $this->actualizeSells();

            $botactive = ($config['botactive'] == 1 ? true : false);
            if (!$botactive) {
                $output->writeln("<info>Bot is not active at the moment</info>");
            } else {
                $currentPrice = $this->gdaxService->getCurrentPrice();

                $output->writeln("** Update positions");

                $this->updatePositions($currentPrice, $output);

                $output->writeln("=== DONE " . date('Y-m-d H:i:s') . " ===");
            }

            sleep(2);
        }
    }
}
