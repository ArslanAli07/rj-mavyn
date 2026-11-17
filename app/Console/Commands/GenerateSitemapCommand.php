<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate sitemap.xml and robots.txt in the public directory';

    public function handle(SitemapService $sitemap): int
    {
        if (app()->environment('local') && str_contains((string) config('app.url'), '.test')) {
            $this->error('APP_URL is a local development URL. Set APP_URL to the production domain before generating a deployable sitemap.');

            return self::FAILURE;
        }

        $result = $sitemap->generate();

        $this->info("Sitemap written to {$result['sitemap']} ({$result['urls']} URLs)");
        $this->info("Robots written to {$result['robots']}");

        return self::SUCCESS;
    }
}
