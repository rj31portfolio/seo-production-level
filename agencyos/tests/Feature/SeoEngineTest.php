<?php

namespace Tests\Feature;

use App\Services\Seo\PageAnalyzer;
use App\Services\Seo\PublicUrl;
use App\Services\Seo\RobotsParser;
use App\Services\Seo\SafeFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_url_validation_blocks_internal_and_ambiguous_destinations(): void
    {
        foreach (['http://127.0.0.1', 'http://10.0.0.1', 'http://169.254.169.254/latest/', 'http://[::1]', 'http://[::ffff:127.0.0.1]', 'http://[64:ff9b::7f00:1]', 'http://2130706433', 'http://example.com:22', 'https://user:password@example.com', 'file:///etc/passwd'] as $url) {
            try {
                app(PublicUrl::class)->resolve($url);
                $this->fail('Unsafe destination accepted: '.$url);
            } catch (\RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $urls = \Mockery::mock(PublicUrl::class)->makePartial();
        $urls->shouldReceive('addresses')->with('mixed.example.com')->andReturn(['8.8.8.8', '192.168.1.2']);
        $this->expectException(\RuntimeException::class);
        $urls->resolve('https://mixed.example.com');
    }

    public function test_redirects_are_resolved_again_and_private_redirects_are_not_requested(): void
    {
        $urls = \Mockery::mock(PublicUrl::class)->makePartial();
        $urls->shouldReceive('addresses')->with('public.example.com')->andReturn(['8.8.8.8']);
        $fetcher = \Mockery::mock(SafeFetcher::class, [$urls])->makePartial()->shouldAllowMockingProtectedMethods();
        $fetcher->shouldReceive('request')->once()->andReturn(['status' => 302, 'headers' => ['location' => 'http://127.0.0.1/private'], 'body' => '', 'response_ms' => 1, 'bytes' => 0]);
        $this->expectException(\RuntimeException::class);
        $fetcher->fetch('https://public.example.com');
    }

    public function test_robots_wildcards_specific_groups_and_allow_ties(): void
    {
        $parser = app(RobotsParser::class);
        $rules = $parser->parse("User-agent: *\nDisallow: /private\nAllow: /private/public\nDisallow: /*?secret=*\nSitemap: https://example.com/sitemap.xml\nUser-agent: SEOAgencyOS\nDisallow: /only\nUser-agent: SEOAgencyOS\nAllow: /only/yes$");
        $this->assertFalse($parser->allowed($rules, 'https://example.com/only/no'));
        $this->assertTrue($parser->allowed($rules, 'https://example.com/only/yes'));
        $this->assertTrue($parser->allowed($rules, 'https://example.com/private', 'seoagencyos'));
        $this->assertFalse($parser->allowed($rules, 'https://example.com/private', 'otherbot'));
        $this->assertTrue($parser->allowed($rules, 'https://example.com/private/public', 'otherbot'));
        $this->assertFalse($parser->allowed($rules, 'https://example.com/a?secret=x', 'otherbot'));
    }

    public function test_html_analysis_and_score_are_derived_from_real_checks(): void
    {
        $html = '<html><head><title>Actual SEO page title</title><meta name="description" content="Actual description"><link rel="canonical" href="/page"><script type="application/ld+json">{"@type":"Article","headline":"Article title"}</script></head><body><h1>Heading</h1><h3>Skipped heading</h3><a href="/next">Next</a><img src="/image.png"><script>invented invisible words</script><p>Useful actual content.</p></body></html>';
        $analyzer = app(PageAnalyzer::class);
        $data = $analyzer->analyze(['url' => 'https://example.com/page', 'body' => $html, 'status' => 200, 'headers' => ['content-type' => 'text/html'], 'response_ms' => 100, 'bytes' => strlen($html), 'redirects' => []]);
        $this->assertSame('Actual SEO page title', $data['title']);
        $this->assertSame('Actual description', $data['description']);
        $this->assertSame(7, $data['metrics']['word_count']);
        $this->assertSame(1, $data['metrics']['missing_alt']);
        $this->assertSame(['https://example.com/page'], $data['canonicals']);
        $checks = array_column($data['checks'], null, 'rule');
        $this->assertFalse($checks['heading_hierarchy']['passed']);
        $this->assertFalse($checks['image_alt_attributes']['passed']);
        $this->assertFalse($checks['description_length']['passed']);
        $this->assertFalse($checks['viewport_present']['passed']);
        $this->assertFalse($checks['document_language']['passed']);
        $this->assertSame('Article', $data['schema'][0]['type']);
        $score = $analyzer->score($data['checks']);
        $this->assertGreaterThan(0, $score['overall']);
        $this->assertLessThan(100, $score['overall']);
        $passed = array_map(fn ($c) => array_replace($c, ['passed' => true]), $data['checks']);
        $this->assertSame(100.0, $analyzer->score($passed)['overall']);
    }

    public function test_mobile_metadata_and_language_checks_use_collected_html(): void
    {
        $html = '<html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="A useful description of this page and the information readers can find here."></head><body><h1>Page topic</h1></body></html>';
        $data = app(PageAnalyzer::class)->analyze(['url' => 'https://example.com/', 'body' => $html, 'status' => 200, 'headers' => [], 'response_ms' => 10, 'bytes' => strlen($html), 'redirects' => []]);
        $checks = array_column($data['checks'], null, 'rule');
        $this->assertTrue($checks['description_length']['passed']);
        $this->assertTrue($checks['viewport_present']['passed']);
        $this->assertTrue($checks['document_language']['passed']);
    }
}
