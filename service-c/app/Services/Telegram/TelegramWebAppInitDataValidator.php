<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Telegram\TelegramWebAppInitDataValidatorInterface;
use App\DTO\Telegram\TelegramWebAppInitDataDto;
use App\Exceptions\Telegram\TelegramWebAppInitDataException;

/**
 * Валидация подписи и срока действия initData Telegram WebApp.
 *
 * Из data-check-string исключается только hash. Поле signature (Bot API 8+)
 * входит в HMAC — подтверждено runtime (real Telegram initData).
 */
class TelegramWebAppInitDataValidator implements TelegramWebAppInitDataValidatorInterface
{
    /**
     * Ключи, не входящие в data-check-string для bot-token HMAC.
     *
     * @var list<string>
     */
    private const EXCLUDED_FROM_CHECK_STRING = ['hash'];

    public function __construct(
        private readonly ApplicationConfigInterface $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function validate(string $initData): TelegramWebAppInitDataDto
    {
        $initData = trim($initData);

        if ($initData === '') {
            throw TelegramWebAppInitDataException::invalid('initData is empty.');
        }

        $botToken = (string) $this->config->get('telegram.bot_token');

        if ($botToken === '') {
            throw TelegramWebAppInitDataException::invalid('TELEGRAM_BOT_TOKEN is not configured.');
        }

        $params = $this->parseInitData($initData);
        $hashEntries = array_values(array_filter(
            $params,
            static fn (array $entry): bool => $entry[0] === 'hash',
        ));

        if (count($hashEntries) !== 1 || ! is_string($hashEntries[0][1] ?? null)) {
            throw TelegramWebAppInitDataException::invalid('initData must contain exactly one hash parameter.');
        }

        $expectedHash = (string) $hashEntries[0][1];

        foreach ($params as $index => $entry) {
            $params[$index][1] = rawurldecode($entry[1]);
        }

        usort($params, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));

        $dataCheckString = implode("\n", array_map(
            static fn (array $entry): string => $entry[0].'='.$entry[1],
            array_values(array_filter(
                $params,
                static fn (array $entry): bool => ! in_array($entry[0], self::EXCLUDED_FROM_CHECK_STRING, true),
            )),
        ));

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $actualHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($expectedHash, $actualHash)) {
            throw TelegramWebAppInitDataException::invalid('initData hash mismatch.');
        }

        $paramsByKey = [];

        foreach ($params as [$key, $value]) {
            if (in_array($key, self::EXCLUDED_FROM_CHECK_STRING, true)) {
                continue;
            }

            $paramsByKey[$key] = $value;
        }

        $authDateRaw = $paramsByKey['auth_date'] ?? null;

        if (! is_string($authDateRaw) || ! ctype_digit($authDateRaw)) {
            throw TelegramWebAppInitDataException::invalid('initData auth_date is missing or invalid.');
        }

        $authDate = (int) $authDateRaw;
        $maxAgeSeconds = (int) $this->config->get('telegram.miniapp.auth_date_max_age_seconds', 3_600);

        if ($authDate > time()) {
            throw TelegramWebAppInitDataException::invalid('initData auth_date is in the future.');
        }

        if ((time() - $authDate) > $maxAgeSeconds) {
            throw TelegramWebAppInitDataException::expired($authDate, $maxAgeSeconds);
        }

        $userRaw = $paramsByKey['user'] ?? null;

        if (! is_string($userRaw) || $userRaw === '') {
            throw TelegramWebAppInitDataException::invalid('initData user is missing.');
        }

        $user = json_decode($userRaw, true);

        if (! is_array($user)) {
            throw TelegramWebAppInitDataException::invalid('initData user is not valid JSON.');
        }

        $telegramUserId = $user['id'] ?? null;

        if (! is_int($telegramUserId) && ! (is_string($telegramUserId) && ctype_digit($telegramUserId))) {
            throw TelegramWebAppInitDataException::invalid('initData user.id is missing or invalid.');
        }

        $firstName = $user['first_name'] ?? null;

        if (! is_string($firstName) || $firstName === '') {
            throw TelegramWebAppInitDataException::invalid('initData user.first_name is missing.');
        }

        return new TelegramWebAppInitDataDto(
            telegramUserId: (int) $telegramUserId,
            firstName: $firstName,
            lastName: is_string($user['last_name'] ?? null) ? $user['last_name'] : null,
            username: is_string($user['username'] ?? null) ? $user['username'] : null,
            languageCode: is_string($user['language_code'] ?? null) ? $user['language_code'] : null,
            photoUrl: is_string($user['photo_url'] ?? null) ? $user['photo_url'] : null,
            authDate: $authDate,
            queryId: isset($paramsByKey['query_id']) ? (string) $paramsByKey['query_id'] : null,
        );
    }

    /**
     * Разбирает строку initData в список пар ключ/значение.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function parseInitData(string $initData): array
    {
        $params = [];
        $seenKeys = [];

        foreach (explode('&', $initData) as $chunk) {
            if ($chunk === '') {
                continue;
            }

            $parts = explode('=', $chunk, 2);

            if (count($parts) !== 2) {
                throw TelegramWebAppInitDataException::invalid('initData contains a malformed key=value pair.');
            }

            [$key, $value] = $parts;

            if ($key === '') {
                throw TelegramWebAppInitDataException::invalid('initData contains an empty parameter key.');
            }

            if (isset($seenKeys[$key])) {
                throw TelegramWebAppInitDataException::invalid(sprintf('initData parameter "%s" is duplicated.', $key));
            }

            $seenKeys[$key] = true;
            $params[] = [$key, $value];
        }

        if ($params === []) {
            throw TelegramWebAppInitDataException::invalid('initData does not contain any parameters.');
        }

        return $params;
    }
}
