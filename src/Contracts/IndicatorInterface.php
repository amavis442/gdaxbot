<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 09-01-18
 * Time: 17:11
 */

namespace App\Contracts;


interface IndicatorInterface
{
    const SELL = -1;
    const HOLD = 0;
    const BUY  = 1;
}