<?php

/**
 * Created by PhpStorm.
 * User: joeldg
 * Date: 6/25/17
 * Time: 1:46 PM
 */

namespace App\Traits;

use App\Util\BrokersUtil;
use App\Util\Console;
use App\Util\Indicators;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Class Signals
 *
 * @package Bowhead\Traits
 *          Forex signals
 *
 *          RSI (14)
 *          Stoch (9,6)
 *          STOCHRS(14)
 *          MACD(12,26)
 *          ADX(14)
 *          Williams %R
 *          CCI(14)
 *          ATR(14)
 *          Highs/Lows(14)
 *          Ultimate Oscillator
 *          ROC
 *          Bull/Bear Power(13) Elder-Ray
 */
trait Signals
{

    /**
     * @var
     */
    protected $indicators;

    /**
     * 
     * @param array $data
     * @return type
     */
    public function signals(array $data)
    {
        if (empty($instruments)) {
            $instruments = ['BTC-EUR'];
        }

        $flags                    = $this->getSymbols($data);
        $transformedFlags         = $this->transformSymbols($flags);
        $transformedSymbolsToText = $this->transformSymbolsToText($transformedFlags);


        return ['flags' => $flags, 'ret' => $transformedFlags, 'strength' => $transformedSymbolsToText];
    }

    /**
     * 
     * @param array $data
     * @return array
     * @throws RuntimeException
     */
    public function getSymbols(array $data): array
    {
        if (is_null($this->indicators)) {
            throw new RuntimeException('Need App\Util\Indicators::class to work');
        }

        $indicators = $this->indicators;

        $flags             = [];
        $flags['rsi']      = $indicators->rsi($data);
        $flags['stoch']    = $indicators->stoch($data);
        $flags['stochrsi'] = $indicators->stochrsi($data);
        $flags['macd']     = $indicators->macd($data);
        $flags['adx']      = $indicators->adx($data);
        $flags['willr']    = $indicators->willr($data);
        $flags['cci']      = $indicators->cci($data);
        $flags['atr']      = $indicators->atr($data);
        $flags['hli']      = $indicators->hli($data);
        $flags['ultosc']   = $indicators->ultosc($data);
        $flags['roc']      = $indicators->roc($data);
        $flags['er']       = $indicators->er($data);

        return $flags;
    }

    /**
     * 
     * @param array $flags
     * @return array
     */
    public function transformSymbols(array $flags): array
    {
        $ret         = [];
        $ret['buy']  = 0;
        $ret['sell'] = 0;
            
        foreach ($flags as $flag) {
            


            $ret['buy']  += ($flag == 1 ? 1 : 0);
            $ret['sell'] += ($flag == -1 ? 1 : 0);
        }

        return $ret;
    }

    public function transformSymbolsToText(array $r): string
    {
        $ret = 'None';

        if ($r['buy'] > 6) {
            $ret = 'WEAK BUY';
        }
        if ($r['buy'] > 8) {
            $ret = 'GOOD BUY';
        }
        if ($r['buy'] > 9) {
            $ret = 'STRONG BUY';
        }
        if ($r['buy'] > 10) {
            $ret = 'VERY STRONG BUY';
        }


        if ($r['sell'] > 6) {
            $ret = 'WEAK SELL';
        }
        if ($r['sell'] > 8) {
            $ret = 'GOOD SELL';
        }
        if ($r['sell'] > 9) {
            $ret = 'STRONG SELL';
        }
        if ($r['sell'] > 10) {
            $ret = 'VERY STRONG SELL';
        }


        return $ret;
    }

}
