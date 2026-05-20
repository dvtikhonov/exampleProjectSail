<?php
namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MicroserviceHttpClient
{
    protected PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::timeout(10)
            ->retry(3, 100)
            ->withToken($this->getAccessToken());
    }

    /**
     * Получение токена текущего пользователя (если он авторизован)
     */
    protected function getAccessToken(): ?string
    {
        $request = request();
        $token = $request->bearerToken() ?: $request->session()->get('passport_token');

        if ($token) {
            return $token;
        }

        $user = auth()->user();
        if (!$user) {
            return null;
        }

        // Генерируем короткоживущий токен для внутренних вызовов
        $token = $user->createToken('internal_api', ['*'])->accessToken;
        return $token;
    }

    /**
     * Вызов сервиса A
     */
    public function serviceA(string $method, string $uri, array $data = []): Response
    {
        $url = config('services.service_a.gateway_url') . '/' . ltrim($uri, '/');
        return $this->client->$method($url, $data);
    }

    /**
     * Вызов сервиса B
     */
    public function serviceB(string $method, string $uri, array $data = []): Response
    {
        $url = config('services.service_b.gateway_url') . '/' . ltrim($uri, '/');
        return $this->client->$method($url, $data);
    }
}
