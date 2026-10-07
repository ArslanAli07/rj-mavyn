<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\SitemapService;

class CategoryObserver
{
    public function __construct(private SitemapService $sitemap) {}

    public function saved(Category $category): void
    {
        $this->sitemap->generateQuietly();
    }

    public function deleted(Category $category): void
    {
        $this->sitemap->generateQuietly();
    }
}
