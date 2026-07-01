<?php

declare(strict_types=1);

namespace App\Services\UrlShortener;

use App\Contracts\UrlShortener\OriginalUrlReachabilityCheckerInterface;
use App\DTO\UrlShortener\OriginalUrlReachabilityResultDto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Проверяет исходный URL через HTTP HEAD/GET с учётом редиректов.
 */
class HttpOriginalUrlReachabilityChecker implements OriginalUrlReachabilityCheckerInterface
{
    private const int TIMEOUT_SECONDS = 10;

    private const int MAX_REDIRECTS = 5;

    /** @var int[] Коды, при которых HEAD часто не поддерживается — пробуем GET. */
    private const array HEAD_UNSUPPORTED_STATUSES = [405, 501];

    public function check(string $url): OriginalUrlReachabilityResultDto
    {
        try {
            $response = $this->request('head', $url);

            if (in_array($response->status(), self::HEAD_UNSUPPORTED_STATUSES, true)) {
                $response = $this->request('get', $url);
            }

            $statusCode = $response->status();

            return new OriginalUrlReachabilityResultDto(
                isReachable: $statusCode === 200,
                httpStatusCode: $statusCode,
            );
        } catch (ConnectionException) {
            return new OriginalUrlReachabilityResultDto(
                isReachable: false,
                httpStatusCode: null,
            );
        }
    }

    /**
     * @param  'head'|'get'  $method
     */
    private function request(string $method, string $url): Response
    {
        return Http::timeout(self::TIMEOUT_SECONDS)
            ->withOptions([
                'allow_redirects' => [
                    'max' => self::MAX_REDIRECTS,
                    'track_redirects' => true,
                ],
            ])
            ->{$method}($url);
    }
}
