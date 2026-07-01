<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\UrlShortener\UrlShortenerServiceInterface;
use App\Exceptions\UrlShortener\ShortLinkNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Публичный редирект по короткому коду.
 */
class ShortLinkRedirectController extends Controller
{
    public function __construct(
        private readonly UrlShortenerServiceInterface $urlShortenerService,
    ) {}

    /**
     * GET /{code} — публичный редирект 302 на original_url.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        try {
            $shortLink = $this->urlShortenerService->resolveRedirect(
                code: $code,
                ipAddress: (string) $request->ip(),
            );
        } catch (ShortLinkNotFoundException) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return redirect()->away($shortLink->original_url, Response::HTTP_FOUND);
    }
}
