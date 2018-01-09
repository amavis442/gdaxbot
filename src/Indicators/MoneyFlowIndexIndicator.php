<?php

namespace App\Indicators;

use App\Contracts\IndicatorInterface;

/**
 * 
 * What is the 'Money Flow Index - MFI'
 * 
 * The money flow index (MFI) is a momentum indicator that measures the inflow and 
 * outflow of money into a security over a specific period of time. The MFI uses a 
 * stock's price and volume to measure trading pressure. Because the MFI adds trading 
 * volume to the relative strength index (RSI), it's sometimes referred to as volume-weighted RSI. 
 * 
 * @see https://www.investopedia.com/terms/m/mfi.asp
 */
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
            throw new \RuntimeException('Not enough data points');
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