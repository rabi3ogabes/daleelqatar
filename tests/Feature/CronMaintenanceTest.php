<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CronMaintenanceTest extends TestCase
{
    public function test_endpoint_is_hidden_when_no_secret_is_configured(): void
    {
        config(['security.cron_secret' => null]);

        $this->get('/cron/maintenance')->assertNotFound();
        $this->withHeader('Authorization', 'Bearer ')->get('/cron/maintenance')->assertNotFound();
    }

    public function test_requests_without_the_bearer_secret_are_rejected(): void
    {
        config(['security.cron_secret' => 'cron-test-secret']);

        $this->get('/cron/maintenance')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer wrong')->get('/cron/maintenance')->assertUnauthorized();
        $this->withHeader('Authorization', 'cron-test-secret')->get('/cron/maintenance')->assertUnauthorized();
    }

    public function test_authorized_cron_runs_housekeeping_and_warms_the_sitemap(): void
    {
        config(['security.cron_secret' => 'cron-test-secret']);
        Cache::forget('sitemap.xml');

        $this->withHeader('Authorization', 'Bearer cron-test-secret')
            ->get('/cron/maintenance')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'commands' => [
                    'daleel:sitemap' => 'ok',
                    'sanctum:prune-expired' => 'ok',
                    'queue:prune-failed' => 'ok',
                    'auth:clear-resets' => 'ok',
                    'model:prune' => 'ok',
                    'analytics:prune' => 'ok',
                    'categories:check' => 'ok',
                ],
            ]);

        $this->assertTrue(Cache::has('sitemap.xml'));
    }

    public function test_robots_txt_keeps_crawlers_out_of_the_cron_endpoint(): void
    {
        $this->app['env'] = 'production';

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /cron/');
    }
}
