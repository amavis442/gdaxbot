<?php

namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 * This algorithm uses the talib Bollinger Bands function to determine entry entry
 * points for long and sell/short positions.
 *
 * When the price breaks out of the upper Bollinger band, a sell or short position
 * is opened. A long position is opened when the price dips below the lower band.
 *
 *
 * Used to measure the market’s volatility.
 * They act like mini support and resistance levels.
 * Bollinger Bounce
 *
 * A strategy that relies on the notion that price tends to always return to the middle of the Bollinger bands.
 * You buy when the price hits the lower Bollinger band.
 * You sell when the price hits the upper Bollinger band.
 * Best used in ranging markets.
 * Bollinger Squeeze
 *
 * A strategy that is used to catch breakouts early.
 * When the Bollinger bands “squeeze”, it means that the market is very quiet, and a breakout is eminent.
 * Once a breakout occurs, we enter a trade on whatever side the price makes its breakout.
 */
class BollingerBandsIndicator implements IndicatorInterface
{

    public function __invoke(array $data, int $period = 10, int $devup = 2, int $devdn = 2): int
    {

        $data2 = $data;
        
        // $prev_close = array_pop($data2['close']); #[count($data['close']) - 2]; // prior close
        $current = array_pop($data2['close']); #[count($data['close']) - 1];    // we assume this is current
        
        // array $real [, integer $timePeriod [, float $nbDevUp [, float $nbDevDn [, integer $mAType ]]]]
        $bbands = trader_bbands(
            $data['close'], 
            $period, 
            $devup, 
            $devdn, 
            0);
        
        if (false === $bbands) {
            throw new \RuntimeException('Not enough data points');
        }
                
        $upper  = $bbands[0];
        
        // $middle = $bbands[1]; 
        // we'll find a use for you, one day
        $lower = $bbands[2];

        // If price is below the recent lower band
        if ($current <= array_pop($lower)) {
            return static::BUY;
            // If price is above the recent upper band
        } elseif ($current >= array_pop($upper)) {
            return static::SELL; 
        } else {
            return static::HOLD; 
        }
    }

}
