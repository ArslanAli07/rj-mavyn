<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\SitemapService;

class ProductObserver
{
    public function __construct(private SitemapService $sitemap) {}

    public function saved(Product $product): void
    {
        $this->sitemap->generateQuietly();
    }

    public function deleted(Product $product): void
    {
        $this->sitemap->generateQuietly();
    }
}
