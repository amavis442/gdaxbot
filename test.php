<?php

require __DIR__.'/bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;

$gdaxService = new \App\Services\GDaxService();

$gdaxService->setCoin(getenv('CRYPTOCOIN'));
//$gdaxService->connect(true);
//$book = $gdaxService->getOrderbook();
//$trades = $gdaxService->getTrades();
//var_dump($trades);



// create a new item getting it from the cache
$numProducts = $cache->getItem('stats.num_products');

// assign a value to the item and save it
$numProducts->set(4712);
$cache->save($numProducts);

// retrieve the cache item
$numProducts = $cache->getItem('stats.num_products');

if (!$numProducts->isHit()) {
    // ... item does not exists in the cache
}
// retrieve the value stored by the item
$total = $numProducts->get();

var_dump($total);




class test {
    public function getRecord() {
        $data = DB::table('settings')->select(DB::raw('MAX(id) AS timeid'))->first();
        
        return $data;
    }
}

$t = new test();
$data = $t->getRecord();
var_dump($data->timeid);
