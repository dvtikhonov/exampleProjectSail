<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Max\MaxWebAppInitDataValidatorInterface;
use App\DTO\Max\MaxWebAppInitDataDto;
use App\Exceptions\Max\MaxWebAppInitDataException;
use Illuminate\Contracts\Config\Repository;

class MaxWebAppInitDataValidator implements MaxWebAppInitDataValidatorInterface
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function validate(string $initData): MaxWebAppInitDataDto
    {
        $initData = trim($initData);

        if ($initData === '') {
            throw MaxWebAppInitDataException::invalid('initData is empty.');
        }

        $botToken = (string) $this->config->get('max.bot_access_token');

        if ($botToken === '') {
            throw MaxWebAppInitDataException::invalid('MAX_BOT_ACCESS_TOKEN is not configured.');
        }

        $params = $this->parseInitData($initData);
        $hashEntries = array_values(array_filter(
            $params,
            static fn (array $entry): bool => $entry[0] === 'hash',
        ));

        if (count($hashEntries) !== 1 || ! is_string($hashEntries[0][1] ?? null)) {
            throw MaxWebAppInitDataException::invalid('initData must contain exactly one hash parameter.');
        }

        $expectedHash = (string) $hashEntries[0][1];

        foreach ($params as $index => $entry) {
            $params[$index][1] = rawurldecode($entry[1]);
        }

        usort($params, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));

        $launchParams = implode("\n", array_map(
            static fn (array $entry): string => $entry[0].'='.$entry[1],
            array_values(array_filter(
                $params,
                static fn (array $entry): bool => $entry[0] !== 'hash',
            )),
        ));

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $actualHash = hash_hmac('sha256', $launchParams, $secretKey);

        if (! hash_equals($expectedHash, $actualHash)) {
            throw MaxWebAppInitDataException::invalid('initData hash mismatch.');
        }

        $paramsByKey = [];

        foreach ($params as [$key, $value]) {
            if ($key === 'hash') {
                continue;
            }

            $paramsByKey[$key] = $value;
        }

        $authDateRaw = $paramsByKey['auth_date'] ?? null;

        if (! is_string($authDateRaw) || ! ctype_digit($authDateRaw)) {
            throw MaxWebAppInitDataException::invalid('initData auth_date is missing or invalid.');
        }

        $authDate = (int) $authDateRaw;
        $maxAgeSeconds = (int) $this->config->get('max.miniapp.auth_date_max_age_seconds', 86_400);

        if ($authDate > time()) {
            throw MaxWebAppInitDataException::invalid('initData auth_date is in the future.');
        }

        if ((time() - $authDate) > $maxAgeSeconds) {
            throw MaxWebAppInitDataException::expired($authDate, $maxAgeSeconds);
        }

        $userRaw = $paramsByKey['user'] ?? null;

        if (! is_string($userRaw) || $userRaw === '') {
            throw MaxWebAppInitDataException::invalid('initData user is missing.');
        }

        $user = json_decode($userRaw, true);

        if (! is_array($user)) {
            throw MaxWebAppInitDataException::invalid('initData user is not valid JSON.');
        }

        $maxUserId = $user['id'] ?? null;

        if (! is_int($maxUserId) && ! (is_string($maxUserId) && ctype_digit($maxUserId))) {
            throw MaxWebAppInitDataException::invalid('initData user.id is missing or invalid.');
        }

        $firstName = $user['first_name'] ?? null;

        if (! is_string($firstName) || $firstName === '') {
            throw MaxWebAppInitDataException::invalid('initData user.first_name is missing.');
        }

        $chat = null;

        if (isset($paramsByKey['chat'])) {
            $chatDecoded = json_decode((string) $paramsByKey['chat'], true);

            if (! is_array($chatDecoded)) {
                throw MaxWebAppInitDataException::invalid('initData chat is not valid JSON.');
            }

            $chat = $chatDecoded;
        }

        return new MaxWebAppInitDataDto(
            maxUserId: (int) $maxUserId,
            firstName: $firstName,
            lastName: is_string($user['last_name'] ?? null) ? $user['last_name'] : null,
            username: is_string($user['username'] ?? null) ? $user['username'] : null,
            languageCode: is_string($user['language_code'] ?? null) ? $user['language_code'] : null,
            photoUrl: is_string($user['photo_url'] ?? null) ? $user['photo_url'] : null,
            authDate: $authDate,
            queryId: isset($paramsByKey['query_id']) ? (string) $paramsByKey['query_id'] : null,
            ip: isset($paramsByKey['ip']) ? (string) $paramsByKey['ip'] : null,
            chat: $chat,
        );
    }

    /**
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
                throw MaxWebAppInitDataException::invalid('initData contains a malformed key=value pair.');
            }

            [$key, $value] = $parts;

            if ($key === '') {
                throw MaxWebAppInitDataException::invalid('initData contains an empty parameter key.');
            }

            if (isset($seenKeys[$key])) {
                throw MaxWebAppInitDataException::invalid(sprintf('initData parameter "%s" is duplicated.', $key));
            }

            $seenKeys[$key] = true;
            $params[] = [$key, $value];
        }

        if ($params === []) {
            throw MaxWebAppInitDataException::invalid('initData does not contain any parameters.');
        }

        return $params;
    }
}
