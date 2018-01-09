<?php

use App\Contracts\IndicatorInterface;

class MoneyFlowIndexIndicator implements IndicatorInterface
{
    public function __invoke(array $data, int $period = 14): int
    {
        $mfi = trader_mfi(
            $data['high'],
            $data['low'],
            $data['close'],
            $data['volume'],
            $period
        );

        if (false === $mfi) {
            throw new RuntimeException('Not enough data points');
        }

        $mfiValue = array_pop($mfi);

        if ($mfiValue < -10) {
            return static::BUY;
        } elseif ($mfiValue > 80) {
            return static::SELL;
        }

        return static::HOLD;
    }

}