<?php
namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 * Average Directional Movement Index
 *
 * @TODO, this one needs more research for the returns
 * 
 * @see http://www.investopedia.com/terms/a/adx.asp
 *
 * The ADX calculates the potential strength of a trend.
 * It fluctuates from 0 to 100, with readings below 20 indicating a weak trend and readings above 50 signaling a strong trend.
 * ADX can be used as confirmation whether the pair could possibly continue in its current trend or not.
 * ADX can also be used to determine when one should close a trade early. For instance, when ADX starts to slide below 50,
 * it indicates that the current trend is possibly losing steam.
 */
class AverageDirectionalMovementIndexIndicator implements IndicatorInterface
{

    public function __invoke(array $data, int $period = 14): int
    {
        $adx = trader_adx($data['high'], $data['low'], $data['close'], $period);

        if (empty($adx)) {
            return -9;
        }

        $adx = array_pop($adx); //[count($adx) - 1];

        if ($adx > 50) {
            return static::BUY; // overbought
        } elseif ($adx < 20) {
            return static::SELL;  // underbought
        } else {
            return static::HOLD;
        }
    }

}
