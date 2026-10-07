<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Throwable;

class SitemapService
{
    public function sitemapPath(): string
    {
        return public_path('sitemap.xml');
    }

    public function robotsPath(): string
    {
        return public_path('robots.txt');
    }

    /**
     * Build and write sitemap.xml + robots.txt to the public directory.
     *
     * @return array{sitemap: string, robots: string, urls: int}
     */
    public function generate(): array
    {
        $sitemap = $this->buildSitemap();
        $sitemap->writeToFile($this->sitemapPath());

        $robots = $this->buildRobotsTxt();
        file_put_contents($this->robotsPath(), $robots);

        return [
            'sitemap' => $this->sitemapPath(),
            'robots' => $this->robotsPath(),
            'urls' => count($sitemap->getTags()),
        ];
    }

    /**
     * Regenerate quietly — never break the calling request on failure.
     */
    public function generateQuietly(): bool
    {
        try {
            $this->generate();
            return true;
        } catch (Throwable $e) {
            Log::error('Sitemap regeneration failed: ' . $e->getMessage());
            return false;
        }
    }

    public function buildSitemap(): Sitemap
    {
        $sitemap = Sitemap::create();

        $staticPages = [
            ['route' => 'home', 'priority' => 1.0, 'frequency' => Url::CHANGE_FREQUENCY_DAILY],
            ['route' => 'shop', 'priority' => 0.9, 'frequency' => Url::CHANGE_FREQUENCY_DAILY],
            ['route' => 'about', 'priority' => 0.7, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'gallery', 'priority' => 0.7, 'frequency' => Url::CHANGE_FREQUENCY_WEEKLY],
            ['route' => 'contact', 'priority' => 0.6, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'faq', 'priority' => 0.6, 'frequency' => Url::CHANGE_FREQUENCY_MONTHLY],
            ['route' => 'privacy', 'priority' => 0.3, 'frequency' => Url::CHANGE_FREQUENCY_YEARLY],
            ['route' => 'terms', 'priority' => 0.3, 'frequency' => Url::CHANGE_FREQUENCY_YEARLY],
            ['route' => 'returns', 'priority' => 0.3, 'frequency' => Url::CHANGE_FREQUENCY_YEARLY],
            ['route' => 'shipping', 'priority' => 0.3, 'frequency' => Url::CHANGE_FREQUENCY_YEARLY],
        ];

        foreach ($staticPages as $page) {
            if (! \Illuminate\Support\Facades\Route::has($page['route'])) {
                continue;
            }

            $sitemap->add(
                Url::create(route($page['route']))
                    ->setPriority($page['priority'])
                    ->setChangeFrequency($page['frequency'])
            );
        }

        foreach (Category::active()->get() as $category) {
            $sitemap->add(
                Url::create(route('category.show', $category->slug))
                    ->setLastModificationDate($category->updated_at)
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            );
        }

        foreach (Product::active()->get() as $product) {
            $url = Url::create(route('product.show', $product->slug))
                ->setLastModificationDate($product->updated_at)
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY);

            if (! empty($product->main_image)) {
                $url->addImage($product->main_image_url, $product->title);
            }

            $sitemap->add($url);
        }

        return $sitemap;
    }

    public function buildRobotsTxt(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /account/',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /password',
            '',
            'Sitemap: ' . url('/sitemap.xml'),
            '',
        ];

        return implode("\n", $lines);
    }
}
