<?php
namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 *      Chande Momentum Oscillator
 */
class ChandeMomentumOscillatorIndicator implements IndicatorInterface
{

    public function __invoke(array $data, int $period = 14): int
    {

        $cmo = trader_cmo($data['close'], $period);
        $cmo = array_pop($cmo); #[count($cmo) - 1];

        if ($cmo > 50) {
            return static::SELL; // overbought
        } elseif ($cmo < -50) {
            return static::BUY;  // underbought
        } else {
            return static::HOLD;
        }
    }

}
