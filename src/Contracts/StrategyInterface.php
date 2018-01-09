<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Contracts;

/**
 *
 * @author patrick
 */
interface StrategyInterface {

    public function  getName(): string;

    /**
     * Signal will be buy, sell or hold
     */
    public function getSignal(): int;
    

    public function getMessage(): array;

}
