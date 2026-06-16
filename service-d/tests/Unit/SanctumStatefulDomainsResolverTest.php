<?php

namespace Tests\Unit;

use App\Support\SanctumStatefulDomainsResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class SanctumStatefulDomainsResolverTest extends TestCase
{
    public function test_resolves_placeholder_with_current_request_host(): void
    {
        $request = Request::create(
            'http://yandexmaps.localhost:8080/login',
            'GET',
            server: ['HTTP_HOST' => 'yandexmaps.localhost:8080'],
        );

        $resolver = new SanctumStatefulDomainsResolver;

        $domains = $resolver->resolve($request);

        $this->assertContains('yandexmaps.localhost', $domains);
        $this->assertContains('yandexmaps.localhost:8080', $domains);
    }

    public function test_keeps_explicit_domains_from_env(): void
    {
        $request = Request::create(
            'https://yandexmaps.94-228-117-27.sslip.io/',
            'GET',
            server: ['HTTP_HOST' => 'yandexmaps.94-228-117-27.sslip.io'],
        );

        $resolver = new SanctumStatefulDomainsResolver;

        $domains = $resolver->resolve(
            $request,
            'maps.example.test,__SANCTUM_CURRENT_REQUEST_HOST__',
        );

        $this->assertContains('maps.example.test', $domains);
        $this->assertContains('yandexmaps.94-228-117-27.sslip.io', $domains);
    }

    public function test_strips_scheme_from_env_domains(): void
    {
        $request = Request::create(
            'https://yandexmaps.94-228-117-27.sslip.io/',
            'GET',
            server: ['HTTP_HOST' => 'yandexmaps.94-228-117-27.sslip.io'],
        );

        $resolver = new SanctumStatefulDomainsResolver;

        $domains = $resolver->resolve(
            $request,
            'https://yandexmaps.94-228-117-27.sslip.io,__SANCTUM_CURRENT_REQUEST_HOST__',
        );

        $this->assertContains('yandexmaps.94-228-117-27.sslip.io', $domains);
        $this->assertNotContains('https://yandexmaps.94-228-117-27.sslip.io', $domains);
    }
}
