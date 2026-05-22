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
            ->withHeaders($this->userHeaders())
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
     * @return array<string, string>
     */
    private function userHeaders(): array
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return [];
        }

        return ['X-User-Id' => (string) $userId];
    }

    private function currentUserId(): ?int
    {
        $request = request();
        $userId = $request->attributes->get('auth_user_id') ?: $request->header('X-User-Id');

        if (is_numeric($userId) && (int) $userId > 0) {
            return (int) $userId;
        }

        return auth()->id();
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
