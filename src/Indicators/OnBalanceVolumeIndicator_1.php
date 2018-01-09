<?php
namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 Money flow index
 */
class HilbertTransformInstantaneousTrendlineIndicator implements IndicatorInterface
{

    public function __invoke(array $data, int $period = 14): int
    {

        $mfi = trader_mfi($data['high'], $data['low'], $data['close'], $data['volume'], $period);
        $mfi = array_pop($mfi); #[count($mfi) - 1];

        if ($mfi > 80) {
            return -1; // overbought
        } elseif ($mfi < 10) {
            return 1;  // underbought
        } else {
            return 0;
        }
    }

}
