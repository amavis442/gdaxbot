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

    public function setIndicicators($indicators);
    public function setOrderService($orderService);
    public function setGdaxService($gdaxService);


    /**
     * Settings can be how many orders to place, spread, sellspread etc
     * @param array $settings
     */
    public function settings(array $config = null);
    
    /**
     * Signal will be buy, sell or hold
     */
    public function getSignal();
    
    /**
     * Buy 
     */
    public function createPosition($currentPrice);
    
    /**
     * Sell
     */
    public function closePosition();
}
