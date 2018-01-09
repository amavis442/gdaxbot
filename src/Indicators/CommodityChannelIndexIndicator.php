<?php
namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 *      Commodity Channel Index
 */
class CommodityChannelIndexIndicator implements IndicatorInterface
{

    public function __invoke(array $data, int $period = 14): int
    {

        # array $high , array $low , array $close [, integer $timePeriod ]
        $cci = trader_cci($data['high'], $data['low'], $data['close'], $period);
        $cci = array_pop($cci); #[count($cci) - 1];

        if ($cci > 100) {
            return static::SELL; // overbought
        } elseif ($cci < -100) {
            return static::BUY;  // underbought
        } else {
            return static::HOLD;
        }
    }

}
