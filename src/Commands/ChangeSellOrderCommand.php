<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\Table;

class ChangeSellOrderCommand extends Command {

    protected $conn;

    public function setConn($conn) {
        $this->conn = $conn;
    }

    protected function configure() {
        $this->setName('bot:change:sell')
                ->setDescription('Change an open sell to a new price.')
                ->addArgument('id', InputArgument::REQUIRED, 'The id you see in bot:report:openorders.')
                ->addArgument('price', InputArgument::REQUIRED, 'The new price to sell for like 12000.00 or 300.00 or 300')
                ->setHelp('Keeps relation with the buy order.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $id = $input->getArgument('id');
        if (!is_numeric($id)) {
            $output->writeln('<error>Id should be an integer</error>');
            return false;
        }

        $price = $input->getArgument('price');
        if (!is_float($price) && !is_numeric($price)) {
            $output->writeln('<error>Price should be of form xxxxxxx.xx or xxxxxx</error>');
            return false;
        }



        $orderService = new \App\Services\OrderService($this->conn);
        $sellOrder = $orderService->fetchOrder($id);

        if (!count($sellOrder)) {
            $output->writeln('<error>Order with id not found</error>');
            return false;
        }

        if ($sellOrder['side'] != 'sell') {
            $output->writeln('<error>Order is not a sell order</error>');
            return false;
        }


        if ($sellOrder['status'] != 'open' && $sellOrder['status'] != 'pending') {
            $output->writeln('<error>Order is not an open or pending order</error>');
            return false;
        }

        $parent_id = $sellOrder['parent_id'];
        if ($parent_id > 0) {
            $buyOrder = $orderService->fetchOrder($parent_id);
            $buyvalue = number_format($buyOrder['size'] * $buyOrder['amount'], 3, '.', '');
            $buyprice = $buyOrder['amount'];
        } else {
            $buyvalue = 0.0;
            $buyprice = 0.0;
        }
        $size = number_format($sellOrder['size'], 9, '.', '');

        $currentprice = number_format($sellOrder['amount'], 2, '.', '');
        $newprice = number_format($price, 2, '.', '');

        $currentvalue = number_format($currentprice * $size, 4, '.', '');
        $newvalue = number_format($price * $size, 4, '.', '');

        $currentprofit = number_format($currentvalue - $buyvalue, 3, '.', '');
        $newprofit = number_format($newvalue - $buyvalue, 3, '.', '');

        $rows[] = ['Buy Price', $buyprice, 'Buy value', $buyvalue];
        $rows[] = ['Price', $currentprice, 'Price', $newprice];
        $rows[] = ['Value', $currentvalue, 'Value', $newvalue];
        $rows[] = ['Profit', $currentprofit, 'Profit', $newprofit];

        $table = new Table($output);
        $table
                ->setHeaders(['Old', ' ', 'New', ' '])
                ->setRows($rows);
        $table->render();


        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Change sell order #' . $id . ' with size ' . $size . ' from ' . $currentprice . ' to ' . $newprice . ' ?', false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('No changes done.');
            return;
        }
        $output->writeln('Placing order.');

        $gdaxService = new \App\Services\GDaxService();
        $gdaxService->setCoin(getenv('CRYPTOCOIN'));

        $gdaxService->connect(false);

        $order = $gdaxService->getOrder($sellOrder['order_id']);
        if ($order->getMessage() !== 'NotFound' && ($order->getStatus() == 'open' || $order->getStatus() == 'pending')) {
            $gdaxService->cancelOrder($sellOrder['order_id']);
            $order = $gdaxService->placeLimitSellOrder($size, $newprice);

            if ($order->getMessage() != 'rejected' && ($order->getStatus() == 'pending' || $order->getStatus() == 'open')) {
                $order_id = $order->getId();
                $orderService->insertOrder('sell', $order_id, $size, $price, $order->getStatus(), $parent_id);
                $output->writeln('Placed new cell order with order id: ' . $order_id);
     
                $orderService->updateOrderStatus($id, 'deleted');                
            } else {
                $output->writeln($order->getMessage() . $order->getRejectReason());
            }
        } else {
            $output->writeln($order->getMessage() . $order->getRejectReason());
        }
    }
}
