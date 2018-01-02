<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 02-01-18
 * Time: 15:18
 */

namespace App\Strategies\Traits;


trait TrendingLinesStrategy
{
    public function getStrategy()
    {
        $indicators = $this->indicators;

        $instrument = 'BTC-EUR';
        $recentData = $indicators->getRecentData($instrument);
        $cci        = $indicators->cci($instrument, $recentData);
        $cmo        = $indicators->cmo($instrument, $recentData);
        $mfi        = $indicators->mfi($instrument, $recentData);

        switch ($indicators->httc($instrument, $recentData)) {     # Hilbert Transform - Trend vs Cycle Mode
            case 0:
                $httc = 'Cycling mode';
                break;
            case 1:
                $httc = 'Trending mode';
                break;
        }

        switch ($indicators->htl($instrument, $recentData)) {     # Hilbert Transform - Trend vs Cycle Mode
            case -1:
                $htl = 'Downtrend';
                break;
            case 0:
                $htl = 'Hold';
                break;
            case 1:
                $htl = 'Uptrend';
                break;
        }

        switch ($indicators->hts($instrument, $recentData)) {
            case -1:
                $hts = 'Sell';
                break;
            case 0:
                $hts = 'Hold';
                break;
            case 1:
                $hts = 'Buy';
                break;
        }


        switch ($indicators->mmi($instrument, $recentData)) {     # Hilbert Transform - Trend vs Cycle Mode
            case -1:
                $mmi = 'Not trending';
                break;
            case 0:
                $mmi = 'Hold';
                break;
            case 1:
                $mmi = 'Trending';
                break;
        }


        /** instrument is overbought, we will short */
        if ($cci == -1 && $cmo == -1 && $mfi == -1) {
            $overbought = 1;
            echo "Overbought \n";
        }

        /** It is underbought, we will go LONG */
        if ($cci == 1 && $cmo == 1 && $mfi == 1) {
            $underbought = 1;
            echo "Underbought \n";
        }

        $adx         = $indicators->adx($instrument, $recentData);
        $_sma6       = trader_sma($recentData['close'], 6);
        $sma6        = array_pop($_sma6);
        $prior_sma6  = array_pop($_sma6);
        $_sma40      = trader_sma($recentData['close'], 40);
        $sma40       = array_pop($_sma40);
        $prior_sma40 = array_pop($_sma40);

        /** have the lines crossed? */
        $down_cross = (($prior_sma6 <= $sma40 && $sma6 > $sma40) ? 1 : 0);
        $up_cross   = (($prior_sma40 <= $sma6 && $sma40 > $sma6) ? 1 : 0);

        $buy = 0;
        if ($adx == 1 && $down_cross) {
            $buy = 1;
        }
        $sell = 0;
        if ($adx == 1 && $up_cross) {
            $sell = 1;
        }


        if ($httc == 'Trending mode' && $htl == 'Uptrend' && $mmi == 'Trending') {
            return 'buy';
        } else {
            if ($httc == 'Trending mode' && $htl == 'Downtrend' && $mmi == 'Trending') {
                return 'sell';
            } else {
                return 'hold';
            }
        }
    }
}