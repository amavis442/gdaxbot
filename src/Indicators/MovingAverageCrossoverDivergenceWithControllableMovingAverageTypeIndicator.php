<?php
namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 *      MACD indicator with controllable types and tweakable periods.
 *
 *      TODO This will be for various backtesting and tests
 *      all periods are ranges of 2 to 100,000
 */
class MovingAverageCrossoverDivergenceWithControllableMovingAverageTypeIndicator implements IndicatorInterface
{

    public function __invoke(array $data, int $fastPeriod = 12, int $fastMAType = 0, int $slowPeriod = 26, int $slowMAType = 0, int $signalPeriod = 9, int $signalMAType = 0): int
    {

        $fastMAType   = $this->ma_type($fastMAType);
        $slowMAType   = $this->ma_type($slowMAType);
        $signalMAType = $this->ma_type($signalMAType);

        // Create the MACD signal and pass in the three parameters: fast period, slow period, and the signal.
        // we will want to tweak these periods later for now these are fine.
        $macd     = trader_macdext($data['close'], $fastPeriod, $fastMAType, $slowPeriod, $slowMAType, $signalPeriod, $signalMAType);
        $macd_raw = $macd[0];
        $signal   = $macd[1];
        $hist     = $macd[2];
        
        if (!empty($macd)) {
            $macd = array_pop($macd[0]) - array_pop($macd[1]); #$macd_raw[count($macd_raw)-1] - $signal[count($signal)-1];
            // Close position for the pair when the MACD signal is negative
            if ($macd < 0) {
                return static::SELL;
                // Enter the position for the pair when the MACD signal is positive
            } elseif ($macd > 0) {
                return static::BUY;
            } else {
                return static::HOLD;
            }
        }

        return -2;
    }

}
