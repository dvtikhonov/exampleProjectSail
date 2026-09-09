<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Shared\ApplicationConfigInterface;
use App\Support\Max\MaxPublicAppUrl;

/**
 * Цель для кнопки inline open_app (поле web_app в MAX API).
 */
final class MaxOpenAppTargetResolver
{
    public function __construct(
        private readonly ApplicationConfigInterface $config,
    ) {}

    /**
     * Возвращает URL mini-app для кнопки open_app.
     */
    public function resolveWebApp(): ?string
    {
        $explicit = trim((string) $this->config->get('max.ui_stand.mini_app_url', ''));

        if ($explicit !== '') {
            return $explicit;
        }

        $publicUrl = MaxPublicAppUrl::resolve(
            (string) $this->config->get('max.public_app_url', ''),
            (string) $this->config->get('max.webhook.url', ''),
        );

        if ($publicUrl !== null) {
            return $publicUrl.'/max-app';
        }

        $username = trim((string) $this->config->get('max.bot_username', ''));

        if ($username !== '') {
            return 'https://max.ru/'.$username;
        }

        return null;
    }

    /**
     * Возвращает contact_id бота для кнопки open_app.
     */
    public function resolveContactId(): ?int
    {
        $botUserId = (int) $this->config->get('max.bot_user_id', 0);

        return $botUserId > 0 ? $botUserId : null;
    }
}
