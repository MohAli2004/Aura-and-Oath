<?php

use App\Models\Product;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$n = 0;
$genders = ['women', 'men', 'unisex'];
foreach (Product::query()->orderBy('id')->get() as $product) {
    $product->update(['gender' => $genders[$n % 3]]);
    $n++;
}

echo "Updated {$n} products.\n";
