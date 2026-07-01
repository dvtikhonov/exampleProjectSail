<?php

declare(strict_types=1);

namespace Tests\Feature\UrlShortener;

use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Feature-тесты публичного редиректа GET /{code}. */
class ShortLinkRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_to_original_url_with_302(): void
    {
        $shortLink = ShortLink::factory()->create([
            'code' => 'abc1234',
            'original_url' => 'https://example.com/target',
            'clicks_count' => 0,
        ]);

        $response = $this->get('/abc1234');

        $response->assertRedirect('https://example.com/target');
        $response->assertStatus(302);

        $this->assertSame(1, ShortLinkClick::query()->where('short_link_id', $shortLink->id)->count());
        $this->assertSame(1, $shortLink->fresh()->clicks_count);
    }

    public function test_records_click_with_client_ip_address(): void
    {
        $shortLink = ShortLink::factory()->create([
            'code' => 'iptrack1',
            'original_url' => 'https://example.com/page',
            'clicks_count' => 0,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->get('/iptrack1')
            ->assertRedirect('https://example.com/page');

        $click = ShortLinkClick::query()->where('short_link_id', $shortLink->id)->first();

        $this->assertNotNull($click);
        $this->assertSame('203.0.113.42', $click->ip_address);
        $this->assertNotNull($click->visited_at);
    }

    public function test_increments_clicks_count_on_each_visit(): void
    {
        $shortLink = ShortLink::factory()->create([
            'code' => 'countme1',
            'original_url' => 'https://example.com/hits',
            'clicks_count' => 0,
        ]);

        $this->get('/countme1')->assertRedirect();
        $this->get('/countme1')->assertRedirect();
        $this->get('/countme1')->assertRedirect();

        $shortLink->refresh();

        $this->assertSame(3, $shortLink->clicks_count);
        $this->assertSame(3, ShortLinkClick::query()->where('short_link_id', $shortLink->id)->count());
    }

    public function test_returns_404_for_unknown_code(): void
    {
        ShortLink::factory()->create([
            'code' => 'existing1',
            'original_url' => 'https://example.com',
        ]);

        $this->get('/unknown99')->assertNotFound();
    }

    public function test_does_not_match_reserved_paths_with_too_short_code(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/up')->assertOk();
        $this->get('/abc')->assertNotFound();
    }
}
