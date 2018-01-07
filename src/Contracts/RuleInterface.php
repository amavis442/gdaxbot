<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 05-01-18
 * Time: 16:30
 */

namespace App\Contracts;


interface RuleInterface
{
    public function validate(float $price, float $spread, ?float $lowestBuyPrice = null, ?float $highestBuyPrice = null, ?float $lowestSellPrice = null, ?float $highestSellPrice = null): bool;
}