<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;


class HandleAuthPassport
{
    /**
     * Проверить токен через service-a.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Попытка получить токен из заголовка (API-запросы)
        $token = $request->bearerToken();

        // Если нет — ищем в сессии (для веб-запросов)
        if (!$token) {
//            $token = $request->session()->get('access_token');
            $token = $request->session()->get('passport_token');
        }

        $this->lll($token);


        // Извлекаем токен из заголовка Authorization
//        $token = $request->bearerToken();
//
        if (!$token) {
            // Нет токена – сразу редирект на страницу входа
            return redirect('http://localhost/login');
        }

        // Внутренний запрос к service-a (docker network)
        $response = Http::withToken($token)
            ->timeout(2) // небольшой таймаут
            ->get('http://service-a:8000/api/pingS'); // имя сервиса из docker-compose

        if ($response->successful()) {
            // Токен валиден – продолжаем выполнение запроса
            // (опционально) можно извлечь user_id из ответа service-a
            // и положить в request, чтобы не делать повторных запросов
            $userData = $response->json();
            $request->merge(['auth_user_id' => $userData['user_id'] ?? null]);

            return $next($request);
        }

        // Токен невалиден или service-a вернул ошибку
        // Чистим токен из сессии и редиректим на вход
        $request->session()->forget('passport_token');
        // Любой не-200 ответ (401, 500 и т.д.) – редирект
        return redirect('http://localhost/login');
    }

    private function lll ($tokenSource) {

        $token = $tokenSource;
//        if (!$token) {
//            return response()->json(['error' => 'No token'], 401);
//        }
        // Попробуйте найти валидный токен в БД Passport
        $tokenRecord = DB::table('oauth_access_tokens')
            ->where('id', hash('sha256', explode(' ', $token)[1] ?? '')) // упрощённо, лучше через проверку Passport
            ->first();
        if ($tokenRecord && $tokenRecord->revoked == 0) {
            return response()->json(['status' => 'ok', 'user_id' => $tokenRecord->user_id]);
        }
        return response()->json(['error' => 'Invalid token'], 401);

    }
}
