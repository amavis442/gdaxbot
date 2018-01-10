<?php
declare(strict_types = 1);

namespace App\Rules;

use App\Util\Cache;

/**
 * Description of TrailingSell
 *
 * @author patrickteunissen
 */
class Stoploss
{

    protected $msg = [];

    public function getMessage(): array
    {
        return $this->msg;
    }

    /**
     *
     * @see https://www.investopedia.com/video/play/how-use-trailing-stops/
     *
     * @param float $currentprice
     * @param float $buyprice
     * @param float $stoplossPercentage
     */
    public function trailingStop(int $position_id, float $currentprice, float $buyprice, float $stoplossPercentage = 3, float $takeprofitPercentage = 1): bool
    {
        $stoploss   = (float) ($stoplossPercentage / 100);
        $takeprofit = (float) ($takeprofitPercentage / 100);

        // Hate loss
        $limitStopLoss  = (float) $buyprice * (1 - $stoploss);
        $profitTreshold = $buyprice * (1 + $takeprofit);

        $oldLimitTakeProfit = Cache::get('gdax.takeprofit.' . $position_id, 0);
        $trailingTakeProfit = (float) $currentprice * (1 - $takeprofit); // 97 < 100 < 103, Take loss at 97 and lower

        $this->msg[] = '<info>Bought: ' . $buyprice . '</info>';
        $this->msg[] = '<info>Currentprice: ' . $currentprice . '<info>';
        $this->msg[] = '<info>Stoploss limit: ' . $limitStopLoss . '</info>';
        $this->msg[] = '<info>Profit treshold: ' . $profitTreshold . '</info>';
        $this->msg[] = '<info>Old Trailing stop: ' . $oldLimitTakeProfit . '</info>';
        $this->msg[] = '<info>Trailing stop: ' . $trailingTakeProfit . '</info>';


        if ($trailingTakeProfit > $oldLimitTakeProfit) {
            $this->msg[] = '<info>Update trailing stop: from ' . $oldLimitTakeProfit . ' to ' . $trailingTakeProfit . '</info>';
            Cache::put('gdax.takeprofit.' . $position_id, $trailingTakeProfit, 3600);
        } else {
            if ($currentprice > $oldLimitTakeProfit) {
                $this->msg[] = '<comment>*** Trigger: Profit .... Sell at ' . $currentprice . "</comment>";
                return true;
            } else {
                if ($currentprice < $limitStopLoss) {
                    $this->msg[] = '<error>*** Trigger: Loss .... Sell at ' . $currentprice . "</error>";

                    return true;
                }
            }
        }

        return false;
    }

}
