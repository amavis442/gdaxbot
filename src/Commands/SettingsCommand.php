<?php

// src/Command/CreateUserCommand.php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SettingsCommand extends Command {

    protected $conn;

    public function setConn($conn) {
        $this->conn = $conn;
    }

    protected function configure() {
        $this->setName('gdax:settings')

                // the short description shown while running "php bin/console list"
                ->setDescription('Adjust the settings of the bot or show the current settings.')
                ->addOption('spread', null, InputOption::VALUE_REQUIRED, 'Set the spread.')
                ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Set the size.')
                ->addOption('bottom', null, InputOption::VALUE_REQUIRED, 'Set the bottom.')
                ->addOption('top', null, InputOption::VALUE_REQUIRED, 'Set the top.')
                ->addOption('max', null, InputOption::VALUE_REQUIRED, 'Set the max.')
                ->addOption('list', null, InputOption::VALUE_NONE, 'List current settings')
                ->setHelp('This command allows you to tinker with the settings of the bot...')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        // $input->getArgument('username'))



        if ($input->getOption('list')) {
            $sql = "SELECT * FROM settings order by id limit 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();

            $output->writeln('<info>Spread: ' . $result['spread'] . '</info>');
            $output->writeln('<info>Order size: ' . $result['size'] . '</info>');
            $output->writeln('<info>Bottom: ' . $result['bottom'] . '</info>');
            $output->writeln('<info>Top: ' . $result['top'] . '</info>');
            $output->writeln('<info>Max orders: ' . $result['max_orders'] . '</info>');
        }

        if ($spread = $input->getOption('spread')) {
            $sql = "UPDATE settings SET spread = :spread";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue('spread', $spread);
            $stmt->execute();

            $output->writeln('<info>Spread is now : ' . $spread . '</info>');
        }

        if ($max = $input->getOption('max')) {
            $sql = "UPDATE settings SET max_orders = :maxorders";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue('maxorders', $max);
            $stmt->execute();

            $output->writeln('<info>Max orders is now : ' . $max . '</info>');
        }

        if ($size = $input->getOption('size')) {
            $sql = "UPDATE settings SET size = :size";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue('size', $size);
            $stmt->execute();

            $output->writeln('<info>Order size is now : ' . $size . '</info>');
        }

        if ($bottom = $input->getOption('bottom')) {
            $sql = "UPDATE settings SET bottom = :bottom";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue('bottom', $bottom);
            $stmt->execute();

            $output->writeln('<info>Bottom buy price is now : ' . $bottom . '</info>');
        }

        if ($top = $input->getOption('top')) {
            $sql = "UPDATE settings SET top = :top";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue('top', $top);
            $stmt->execute();

            $output->writeln('<info>Top buy price is now : ' . $top . '</info>');
        }
    }

}
