<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$countries = DB::table('countries')->orderBy('id')->get(['id', 'name_en', 'iso2']);
foreach($countries as $c) { 
    echo $c->id.'|'.$c->name_en.'|'.$c->iso2.PHP_EOL; 
}
