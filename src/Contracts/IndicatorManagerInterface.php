<?php
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace App\Contracts;

use App\Contracts\IndicatorInterface;

interface IndicatorManagerInterface {
    public function add(string $indicator, IndicatorInterface $resolver);
}