<?php

// src/Command/CreateUserCommand.php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Database\Capsule\Manager as DB;

class SettingsCommand extends Command
{

    protected function configure()
    {
        $this->setName('bot:settings')

                // the short description shown while running "php bin/console list"
                ->setDescription('Adjust the settings of the bot or show the current settings.')
                ->addOption('spread', null, InputOption::VALUE_REQUIRED, 'Set the buy spread.')
                ->addOption('sellspread', null, InputOption::VALUE_REQUIRED, 'Set the sell spread.')
                ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Set the size.')
                ->addOption('bottom', null, InputOption::VALUE_REQUIRED, 'Set the bottom.')
                ->addOption('top', null, InputOption::VALUE_REQUIRED, 'Set the top.')
                ->addOption('max', null, InputOption::VALUE_REQUIRED, 'Set the max.')
                ->addOption('lifetime', null, InputOption::VALUE_REQUIRED, 'Set the lifetime.')
                ->addOption('on', null, InputOption::VALUE_NONE, 'Turn bot on.')
                ->addOption('off', null, InputOption::VALUE_NONE, 'Turn bot off.')
                ->addOption('list', null, InputOption::VALUE_NONE, 'List current settings')
                ->setHelp('This command allows you to tinker with the settings of the bot...')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if ($input->getOption('list')) {
            $result = DB::table('settings')->orderBy('id')->limit(1)->first();


            $output->writeln('<info>Size: ' . $result->size . '</info>');
            $output->writeln('<info>Buy Spread: ' . $result->spread . '</info>');
            $output->writeln('<info>Sell Spread: ' . $result->sellspread . '</info>');
            $output->writeln('<info>Order size: ' . $result->size . '</info>');
            $output->writeln('<info>Bottom: ' . $result->bottom . '</info>');
            $output->writeln('<info>Top: ' . $result->top . '</info>');
            $output->writeln('<info>Max orders: ' . $result->max_orders . '</info>');
            $output->writeln('<info>Lifetime: ' . $result->lifetime . '</info>');
            $output->writeln('<info>Bot active: ' . $result->botactive . '</info>');
        }

        if ($spread = $input->getOption('spread')) {
            DB::table('settings')->where('id', 1)->update(['spread' => $spread]);
            $output->writeln('<info>Buy Spread is now : ' . $spread . '</info>');
        }

        if ($active = $input->getOption('on')) {
            DB::table('settings')->where('id', 1)->update(['botactive' => 1]);
            $output->writeln('<info>The bot is on now</info>');
        }

        if ($active = $input->getOption('off')) {
            DB::table('settings')->where('id', 1)->update(['botactive' => 0]);
           $output->writeln('<info>The bot is off now</info>');
        }

        if ($sellspread = $input->getOption('sellspread')) {
            DB::table('settings')->where('id', 1)->update(['sellspread' => $sellspread]);
            $output->writeln('<info>Sell Spread is now : ' . $sellspread . '</info>');
        }

        if ($max = $input->getOption('max')) {
            DB::table('settings')->where('id', 1)->update(['max_orders' => $max]);
            $output->writeln('<info>Max orders is now : ' . $max . '</info>');
        }

        if ($size = $input->getOption('size')) {
            DB::table('settings')->where('id', 1)->update(['size' => $size]);
            $output->writeln('<info>Order size is now : ' . $size . '</info>');
        }

        if ($bottom = $input->getOption('bottom')) {
            DB::table('settings')->where('id', 1)->update(['bottom' => $bottom]);
            $output->writeln('<info>Bottom buy price is now : ' . $bottom . '</info>');
        }

        if ($top = $input->getOption('top')) {
            DB::table('settings')->where('id', 1)->update(['top' => $top]);
            $output->writeln('<info>Top buy price is now : ' . $top . '</info>');
        }

        if ($lifetime = $input->getOption('lifetime')) {
            DB::table('settings')->where('id', 1)->update(['lifetime' => $lifetime]);
             $output->writeln('<info>Lifetime buyorder is now : ' . $lifetime . '</info>');
        }
    }

}
