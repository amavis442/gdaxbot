<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RuleTest extends TestCase
{


//public function validate(float $price, float $spread, float $lowestBuyPrice = null, float $lowestSellPrice = null,float $highestBuyPrice = null, ,float $highestSellPrice = null): bool


    // No buy and no sell order
    public function testNoBuyNoSellOrder()
    {
        $rule = new \App\Rules\PriceIsRightRule();
    
        $result = $rule->validate(14200.00, 60.00);
        
        $this->assertTrue($result);
    }
    
    
    // 1 buy buyorder and no sell order
    public function testOneBuyOrderAndNoSellAndPriceIsOutsideSpread()
    {
        
        $rule = new \App\Rules\PriceIsRightRule();
    
        $result = $rule->validate(14120.00, 60.00, 14200.00);
        
        $this->assertTrue($result);
    }
    
    // 1 buy buyorder and no sell order
    public function testOneBuyOrderAndNoSellAndPriceIsInsideSpread()
    {
        $rule = new \App\Rules\PriceIsRightRule();
    
        $result = $rule->validate(14150.00, 60.00, 14200.00);
        
        $this->assertFalse($result);
    }

    
    
    
    // 1 buy buyorder and no sell order
    public function testOneSellOrderAndNoBuyAndPriceIsOutsideSpread()
    {
        
        $rule = new \App\Rules\PriceIsRightRule();
    
        $result = $rule->validate(14120.00, 60.00, null,null,14200.00, null);
        
        $this->assertTrue($result);
    }
    
    // 1 buy buyorder and no sell order
    public function testOneSellOrderAndNoBuyAndPriceIsInsideSpread()
    {
        $rule = new \App\Rules\PriceIsRightRule();
    
        $result = $rule->validate(14150.00, 60.00, null,null, 14200.00,null);
        
        $this->assertFalse($result);
    }
    
    
    
    // 1 buy buyorder and no sell order
    public function testOneSellOrderAndOneBuyAndPriceIsOutsideSpread()
    {
        
        $rule = new \App\Rules\PriceIsRightRule();
    
        $result = $rule->validate(14060.00, 60.00, 14130,null,14200.00, null);
        
        $this->assertTrue($result);
    }
    
    // 1 buy buyorder and no sell order
    public function testOneSellOrderAnOneBuyAndPriceIsInsideSpread()
    {
        $rule = new \App\Rules\PriceIsRightRule();
    
        $result = $rule->validate(14080.00, 60.00, 14130,null, 14200.00,null);
        
        $this->assertFalse($result);
    }
    
}


