<?php

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Catalog\Models\Product;

test('catalog index renders the catalog page with products', function () {
    Product::factory()->count(3)->create();

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog::index')
            ->has('products', 3)
            ->has('products.0.name')
        );
});
