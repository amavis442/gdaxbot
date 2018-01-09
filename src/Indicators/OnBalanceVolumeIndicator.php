<?php
namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 *      On Balance Volume
 *      http://stockcharts.com/school/doku.php?id=chart_school:technical_indicators:on_balance_volume_obv
 *      signal assumption that volume precedes price on confirmation, divergence and breakouts
 *
 *      use with mfi to confirm
 */
class OnBalanceVolumeIndicator implements IndicatorInterface
{

    public function __invoke(array $data, int $period = 14): int
    {

        $_obv        = trader_obv($data['close'], $data['volume']);
        $current_obv = array_pop($_obv); #[count($_obv) - 1];
        $prior_obv   = array_pop($_obv); #[count($_obv) - 2];
        $earlier_obv = array_pop($_obv); #[count($_obv) - 3];

        /**
         *   This forecasts a trend in the last three periods
         *   TODO: this needs to be tested more, we might need to look closer for crypto currencies
         */
        if (($current_obv > $prior_obv) && ($prior_obv > $earlier_obv)) {
            return static::BUY; // upwards momentum
        } elseif (($current_obv < $prior_obv) && ($prior_obv < $earlier_obv)) {
            return static::SELL; // downwards momentum
        } else {
            return static::HOLD;
        }
    }

}
