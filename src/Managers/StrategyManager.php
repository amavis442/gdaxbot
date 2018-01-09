<?php
/**
 * Created by PhpStorm.
 * User: patrickteunissen
 * Date: 09-01-18
 * Time: 17:26
 */

namespace App\Managers;

use App\Contracts\IndicatorInterface;

class StrategyManager
{
    /**
     *
     * @var type 
     */
    protected $strategies = [];

    /**
     * Add an strategy resolver.
     * 
     * @param string $strategy
     * @param type $resolver
     */
    public function add(string $strategy, $resolver)
    {
        $this->strategies[$strategy] = $resolver;
    }

    /**
     * Dynamically handle the indicator calls.
     *
     * @param string $strategy
     * @param array  $parameters
     *
     * @return int
     */
    public function __call(string $strategy, array $parameters): int
    {
        if (!isset($this->strategies[$strategy])) {
            throw new \BadMethodCallException("Strategy [{$strategy}] does not exist");
        }

        return call_user_func($this->strategies[$strategy],$parameters[0],$parameters[1]);
    }
}
