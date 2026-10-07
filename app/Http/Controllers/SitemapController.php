<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class SitemapController extends Controller
{
    public function __construct(private SitemapService $sitemap) {}

    public function sitemap(): Response
    {
        $path = $this->sitemap->sitemapPath();

        if (! File::exists($path)) {
            $this->sitemap->generate();
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        $path = $this->sitemap->robotsPath();

        if (! File::exists($path)) {
            $this->sitemap->generate();
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
