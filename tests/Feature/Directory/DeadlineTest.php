<?php

namespace Tests\Feature\Directory;

use App\Models\Business;
use App\Support\Deadline;
use App\Support\Discovery\DiscoveryException;
use App\Support\Discovery\Fetcher;
use App\Support\Discovery\UrlGuard;
use App\Support\Enrichment\AiWriter;
use App\Support\Enrichment\ClaudeAiWriter;
use App\Support\Enrichment\Enricher;
use App\Support\Enrichment\EnrichmentException;
use App\Support\Enrichment\WebsiteReader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Long jobs must finish inside the 60-second limit of a serverless request. */
class DeadlineTest extends TestCase
{
    protected function tearDown(): void
    {
        Deadline::clear();
        UrlGuard::resolveUsing(null);
        parent::tearDown();
    }

    public function test_without_a_deadline_nothing_is_limited(): void
    {
        $this->assertFalse(Deadline::active());
        $this->assertSame(INF, Deadline::remaining());
        $this->assertSame(15.0, Deadline::timeout(15));
    }

    public function test_timeouts_shrink_to_the_time_left(): void
    {
        Deadline::start(10);

        $this->assertTrue(Deadline::has(9));
        $this->assertFalse(Deadline::has(11));
        $this->assertLessThanOrEqual(8.0, Deadline::timeout(120));
        $this->assertGreaterThan(7.0, Deadline::timeout(120));
    }

    public function test_the_fetcher_stops_instead_of_running_past_the_deadline(): void
    {
        UrlGuard::resolveUsing(fn () => ['93.184.216.34']);
        Http::fake();
        Deadline::start(1);

        try {
            (new Fetcher(0))->get('https://example.qa', obeyRobots: false);
            $this->fail('Expected the fetch to be refused.');
        } catch (DiscoveryException $e) {
            $this->assertSame('out_of_time', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_the_ai_is_not_called_without_enough_time(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Deadline::start(5);

        $this->expectException(EnrichmentException::class);
        $this->expectExceptionMessage('ai_out_of_time');
        (new ClaudeAiWriter)->categorize([['id' => 1, 'name' => 'Test', 'name_ar' => null, 'labels' => [], 'services' => null, 'description' => null, 'current' => null]], ['cafes' => 'Restaurants › Cafés']);
    }

    public function test_a_website_check_cut_short_is_not_reported_as_unreachable(): void
    {
        UrlGuard::resolveUsing(fn () => ['93.184.216.34']);
        Http::fake();
        $this->app->instance(WebsiteReader::class, new WebsiteReader(0));
        $this->app->instance(AiWriter::class, new FakeAiWriter);
        $business = Business::factory()->published()->create(['website' => 'https://slow.example.qa', 'website_status' => 'found', 'is_demo' => false]);

        Deadline::start(14); // too little for a website visit
        $suggestion = app(Enricher::class)->run($business, ['google' => false, 'website' => true, 'ai' => false]);

        $this->assertSame('out_of_time', $suggestion->notes['website']);
        $this->assertSame('found', $business->fresh()->website_status);
        $this->assertNull($business->fresh()->enriched_at, 'the next run picks it up again');
        Http::assertNothingSent();
    }
}
