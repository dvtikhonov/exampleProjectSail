<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal;

use App\DTO\Max\MaxMiniAppAccessContextDto;
use App\Http\Support\MaxAppRequestContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Валидация POST /internal/messenger-choice (вызов service-j, без initData и секретов).
 *
 * Поля: host обязателен; ip и user_agent ограничены по длине.
 * В лог user_agent попадает обрезанным до 120 символов, как у визита mini-app.
 */
class LogMessengerChoiceRequest extends FormRequest
{
    private const int HOST_MAX_LENGTH = 255;

    private const int IP_MAX_LENGTH = 45;

    /** Верхняя граница тела: в лог всё равно уходит не больше USER_AGENT_LOG_LENGTH. */
    private const int USER_AGENT_MAX_LENGTH = 2048;

    private const int USER_AGENT_LOG_LENGTH = 120;

    public function authorize(): bool
    {
        return true;
    }

    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:'.self::HOST_MAX_LENGTH],
            'ip' => ['nullable', 'string', 'max:'.self::IP_MAX_LENGTH],
            'user_agent' => ['nullable', 'string', 'max:'.self::USER_AGENT_MAX_LENGTH],
        ];
    }

    /**
     * Контекст визита: is_tunnel считается по host из тела, не по Host запроса к service-c.
     */
    public function accessContext(): MaxMiniAppAccessContextDto
    {
        $host = (string) $this->validated('host');
        $userAgent = $this->validated('user_agent');

        return new MaxMiniAppAccessContextDto(
            host: $host,
            isTunnel: $this->isTunnelHost($host),
            ip: $this->nullableString($this->validated('ip')),
            userAgent: Str::limit(is_string($userAgent) ? $userAgent : '', self::USER_AGENT_LOG_LENGTH, '…'),
        );
    }

    /**
     * Тот же признак публичного туннеля, что у MaxAppRequestContext, но для переданного host.
     */
    private function isTunnelHost(string $host): bool
    {
        $probe = Request::create('/', 'GET', server: [
            'HTTP_HOST' => $host,
        ]);

        return MaxAppRequestContext::isPublicTunnelRequest($probe);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
