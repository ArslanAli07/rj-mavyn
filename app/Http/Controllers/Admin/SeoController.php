<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoMeta;
use App\Services\SitemapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SeoController extends Controller
{
    private array $pages = [
        'home'     => 'Home Page',
        'shop'     => 'Shop Page',
        'about'    => 'About Us',
        'contact'  => 'Contact Page',
        'faq'      => 'FAQ Page',
        'gallery'  => 'Gallery Page',
        'privacy'  => 'Privacy Policy',
        'terms'    => 'Terms & Conditions',
        'returns'  => 'Refunds & Returns',
        'shipping' => 'Shipping & Delivery Policy',
    ];

    public function __construct(private SitemapService $sitemap) {}

    public function index(): View
    {
        $metas = SeoMeta::all()->keyBy('page_key');

        $sitemapPath = $this->sitemap->sitemapPath();
        $robotsPath = $this->sitemap->robotsPath();

        $sitemapInfo = [
            'sitemap_exists' => File::exists($sitemapPath),
            'robots_exists' => File::exists($robotsPath),
            'sitemap_url' => url('/sitemap.xml'),
            'robots_url' => url('/robots.txt'),
            'sitemap_updated_at' => File::exists($sitemapPath)
                ? date('M d, Y H:i', File::lastModified($sitemapPath))
                : null,
        ];

        return view('admin.seo.index', compact('metas', 'sitemapInfo'), ['pages' => $this->pages]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'pages.*.meta_title' => 'nullable|string|max:255',
            'pages.*.meta_description' => 'nullable|string|max:500',
            'pages.*.meta_keywords' => 'nullable|string|max:1000',
            'pages.*.og_image' => 'nullable|string|max:2048',
        ]);

        foreach ($this->pages as $key => $label) {
            if ($request->has("pages.{$key}")) {
                SeoMeta::updateOrCreate(
                    ['page_key' => $key],
                    $request->input("pages.{$key}", [])
                );
            }
        }
        return back()->with('success', 'SEO settings updated!');
    }

    public function regenerateSitemap(): RedirectResponse
    {
        try {
            $result = $this->sitemap->generate();

            return back()->with(
                'success',
                "Sitemap regenerated successfully with {$result['urls']} URLs. robots.txt was updated too."
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to regenerate sitemap: ' . $e->getMessage());
        }
    }
}
