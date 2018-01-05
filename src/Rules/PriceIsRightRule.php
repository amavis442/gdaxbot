<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 05-01-18
 * Time: 16:20
 */

namespace App\Rules;


use App\Contracts\RuleInterface;

class PriceIsRightRule implements RuleInterface
{
    public function validate(float $price, float $spread, float $lowestBuyPrice = null, float $highestBuyPrice = null, float $lowestSellPrice = null,float $highestSellPrice = null): bool
    {
        $canPlaceBuyOrder = false;
        if ($lowestBuyPrice || $lowestSellPrice) {
            if (!$lowestBuyPrice && $lowestSellPrice) {
                if ($price < ($lowestSellPrice - $spread)) {
                    $canPlaceBuyOrder = true;
                }
            }

            if ($lowestBuyPrice && !$lowestSellPrice) {
                if ($price < ($lowestBuyPrice - $spread)) {
                    $canPlaceBuyOrder = true;
                }
            }

            if ($lowestBuyPrice && $lowestSellPrice) {
                if ($lowestSellPrice < $lowestBuyPrice && $price < ($lowestSellPrice - $spread)) {
                    $canPlaceBuyOrder = true;
                }

                if ($lowestSellPrice > $lowestBuyPrice && $price < ($lowestBuyPrice - $spread)) {
                    $canPlaceBuyOrder = true;
                }
            }
        }

        if (!$lowestBuyPrice && !$highestBuyPrice && !$lowestSellPrice) { // First order of the day
            $canPlaceBuyOrder = true;
        }

        return $canPlaceBuyOrder;
    }
}