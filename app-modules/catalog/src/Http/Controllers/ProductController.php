<?php

namespace Modules\Catalog\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Catalog\Models\Product;

class ProductController
{
    public function index(): Response
    {
        return Inertia::render('catalog::index', [
            'products' => Product::query()->latest()->get(),
        ]);
    }
}
