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
        $result = $sitemap->generate();

        $this->info("Sitemap written to {$result['sitemap']} ({$result['urls']} URLs)");
        $this->info("Robots written to {$result['robots']}");

        return self::SUCCESS;
    }
}
