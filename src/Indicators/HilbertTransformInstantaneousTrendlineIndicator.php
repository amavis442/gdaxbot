<?php

namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 * Hilbert Transform - Instantaneous Trendline — smoothed trendline, if the
 * price moves 1.5% away from the trendline we can declare a trend.
 * 
 * 
 *      WMA(4)
 *      trader_ht_trendline
 *
 *      if WMA(4) < htl for five periods then in downtrend (sell in trend mode)
 *      if WMA(4) > htl for five periods then in uptrend   (buy in trend mode)
 *
 *      // if price is 1.5% more than trendline, then  declare a trend
 *      (WMA(4)-trendline)/trendline >= 0.15 then trend = 1
 */
class HilbertTransformInstantaneousTrendlineIndicator implements IndicatorInterface
{

    public function __invoke(array $data, int $period = 14): int
    {

        $declared = $uptrend = $downtrend = 0;
        $a_htl    = $a_wma4 = [];
        $htl      = trader_ht_trendline($data['close']);
        $wma4     = trader_wma($data['close'], 4);

        for ($a = 0; $a < 5; $a++) {
            $a_htl[$a]  = array_pop($htl);
            $a_wma4[$a] = array_pop($wma4);
            $uptrend    += ($a_wma4[$a] > $a_htl[$a] ? 1 : 0);
            $downtrend  += ($a_wma4[$a] < $a_htl[$a] ? 1 : 0);

            $declared = (($a_wma4[$a] - $a_htl[$a]) / $a_htl[$a]);
        }
        
        
        if ($uptrend || $declared >= 0.15) {
            return static::BUY;
        }
        
        if ($downtrend || $declared <= 0.15) {
            return static::SELL;
        }

        return static::HOLD;
    }

}
