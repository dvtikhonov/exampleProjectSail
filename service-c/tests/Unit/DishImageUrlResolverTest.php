<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Food\DishImageUrlResolver;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DishImageUrlResolverTest extends TestCase
{
    public function test_resolve_returns_null_for_null(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);

        $this->assertNull($resolver->resolve(null));
    }

    public function test_resolve_returns_null_for_empty_string(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);

        $this->assertNull($resolver->resolve(''));
    }

    public function test_resolve_returns_https_url_unchanged(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);
        $url = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200&h=200&fit=crop';

        $this->assertSame($url, $resolver->resolve($url));
    }

    public function test_resolve_returns_http_url_unchanged(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);
        $url = 'http://cdn.example.test/dishes/pasta.jpg';

        $this->assertSame($url, $resolver->resolve($url));
    }

    public function test_resolve_returns_public_storage_url_for_relative_path(): void
    {
        Storage::fake('public');

        $resolver = $this->app->make(DishImageUrlResolver::class);
        $relativePath = 'dishes/margarita.jpg';

        $this->assertSame(
            Storage::disk('public')->url($relativePath),
            $resolver->resolve($relativePath),
        );
    }
}
