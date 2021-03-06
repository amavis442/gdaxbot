<?php

namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 * Average True Range
 * 
 * http://www.investopedia.com/articles/trading/08/atr.asp
 * The idea is to use ATR to identify breakouts, if the price goes higher than
 * the previous close + ATR, a price breakout has occurred.
 *
 * The position is closed when the price goes 1 ATR below the previous close.
 *
 * This algorithm uses ATR as a momentum strategy, but the same signal can be used for
 * a reversion strategy, since ATR doesn't indicate the price direction (like adx below)
 */
class AverageTrueRangeIndicator implements IndicatorInterface
{

    public function __invoke(array $data, int $period = 14): int
    {

        if ($period > count($data['close'])) {
            $period = round(count($data['close']) / 2);
        }

        $data2      = $data;
        $current    = array_pop($data2['close']); //[count($data['close']) - 1];    // we assume this is current
        $prev_close = array_pop($data2['close']); //[count($data['close']) - 2]; // prior close

        $atr = trader_atr(
            $data['high'], 
            $data['low'], 
            $data['close'], 
            $period);
        
        if (false === $atr) {
            throw new \RuntimeException('Not enough data points');
        }
        
        
        $atr = array_pop($atr); //[count($atr)-1]; 
        // pick off the last
        
        // An upside breakout occurs when the price goes 1 ATR above the previous close
        $upside_signal = ($current - ($prev_close + $atr));

        // A downside breakout occurs when the previous close is 1 ATR above the price
        $downside_signal = ($prev_close - ($current + $atr));

        if ($upside_signal > 0) {
            return static::BUY; 
        } elseif ($downside_signal > 0) {
            return static::SELL;
        }

        return static::HOLD;
    }

}
