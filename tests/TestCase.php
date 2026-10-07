<?php

namespace Tests;

use App\Services\SitemapService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    private ?string $sitemapDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sitemapDirectory = sys_get_temp_dir().'/mavyn-sitemap-tests-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->sitemapDirectory, 0755, true);
        $this->app->instance(SitemapService::class, new class($this->sitemapDirectory) extends SitemapService
        {
            public function __construct(private string $directory) {}

            public function sitemapPath(): string
            {
                return $this->directory.'/sitemap.xml';
            }

            public function robotsPath(): string
            {
                return $this->directory.'/robots.txt';
            }
        });
    }

    protected function tearDown(): void
    {
        try {
            if ($this->sitemapDirectory) {
                File::deleteDirectory($this->sitemapDirectory);
            }
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Merge valid honeypot fields into form data for protected routes.
     */
    protected function withHoneypot(array $data = [], bool $filled = false, ?int $startedSecondsAgo = null): array
    {
        $nameField = config('honeypot.name_field', 'website');
        $timeField = config('honeypot.valid_from_field', '_form_started_at');
        $startedAt = now()->subSeconds($startedSecondsAgo ?? max(1, (int) config('honeypot.min_seconds', 2)))->timestamp;

        return array_merge($data, [
            $nameField => $filled ? 'https://spam.example' : '',
            $timeField => Crypt::encryptString((string) $startedAt),
        ]);
    }
}
