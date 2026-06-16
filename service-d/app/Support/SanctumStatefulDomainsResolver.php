<?php

namespace App\Support;

use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

/**
 * Разрешает список stateful-доменов Sanctum из env и текущего Host запроса.
 */
final class SanctumStatefulDomainsResolver
{
    private const PLACEHOLDER_CURRENT_HOST = '__SANCTUM_CURRENT_REQUEST_HOST__';

    /**
     * @return list<string>
     */
    public function resolve(?Request $request = null, ?string $configuredDomains = null): array
    {
        $configured = $configuredDomains ?? (string) env('SANCTUM_STATEFUL_DOMAINS', '');

        if ($configured === '') {
            return $this->defaultDomains($request);
        }

        $domains = [];

        foreach (explode(',', $configured) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if ($entry === self::PLACEHOLDER_CURRENT_HOST) {
                $domains = array_merge($domains, $this->hostsFromRequest($request));

                continue;
            }

            $normalized = HostNormalizer::normalize($entry);

            if ($normalized !== null) {
                $domains[] = $normalized;
            }
        }

        return array_values(array_unique(array_merge(
            $domains,
            $this->hostsFromRequest($request),
        )));
    }

    /**
     * @return list<string>
     */
    private function defaultDomains(?Request $request): array
    {
        $defaults = [
            'localhost',
            'localhost:3000',
            '127.0.0.1',
            '127.0.0.1:8000',
            '::1',
        ];

        $applicationHost = Sanctum::currentApplicationUrlWithPort();
        if ($applicationHost !== '') {
            $defaults[] = $applicationHost;
        }

        return array_values(array_unique(array_merge(
            $defaults,
            $this->hostsFromRequest($request),
        )));
    }

    /**
     * @return list<string>
     */
    private function hostsFromRequest(?Request $request): array
    {
        if ($request === null) {
            return [];
        }

        $host = $request->getHost();

        if ($host === '') {
            return [];
        }

        $hosts = [$host];

        $port = $request->getPort();

        if ($port && ! in_array($port, [80, 443], true)) {
            $hosts[] = $host.':'.$port;
        }

        return $hosts;
    }
}
