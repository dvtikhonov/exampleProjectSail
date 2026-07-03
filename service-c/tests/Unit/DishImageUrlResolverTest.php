<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Food\DishImageUrlResolver;
use Tests\TestCase;

class DishImageUrlResolverTest extends TestCase
{
    public function test_resolve_public_url_returns_null_for_null(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);

        $this->assertNull($resolver->resolvePublicUrl(1, null));
    }

    public function test_resolve_public_url_returns_null_for_empty_string(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);

        $this->assertNull($resolver->resolvePublicUrl(1, ''));
    }

    public function test_resolve_public_url_includes_storage_version_query_param(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);
        $storagePath = 'dishes/margarita.jpg';

        $url = $resolver->resolvePublicUrl(5, $storagePath);

        $this->assertNotNull($url);
        $this->assertSame(
            '/api/food/dishes/5/image?v='.substr(hash('sha256', $storagePath), 0, 12),
            $url,
        );
    }

    public function test_storage_version_changes_when_storage_path_changes(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);

        $firstUrl = $resolver->resolvePublicUrl(5, 'dishes/5/old.jpg');
        $secondUrl = $resolver->resolvePublicUrl(5, 'dishes/5/new.jpg');

        $this->assertNotSame($firstUrl, $secondUrl);
    }

    public function test_storage_version_is_stable_for_same_storage_path(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);
        $storagePath = 'dishes/5/same.jpg';

        $this->assertSame(
            $resolver->resolvePublicUrl(5, $storagePath),
            $resolver->resolvePublicUrl(5, $storagePath),
        );
    }
}
