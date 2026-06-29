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

    public function test_resolve_public_url_returns_same_origin_path_for_storage_path(): void
    {
        $resolver = $this->app->make(DishImageUrlResolver::class);

        $this->assertSame(
            '/api/food/dishes/5/image',
            $resolver->resolvePublicUrl(5, 'dishes/margarita.jpg'),
        );
    }
}
