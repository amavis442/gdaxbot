<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 09-01-18
 * Time: 10:41
 */

namespace App\Contracts;


interface BotInterface
{
    /**
     * @param $container
     *
     * @return mixed
     */
    public function setContainer($container);

    /**
     * @param array $config
     *
     * @return mixed
     */
    public function setSettings(array $config = []);

    public function getMessage(): array;

    /**
     * @return array
     */
    public function run();
}