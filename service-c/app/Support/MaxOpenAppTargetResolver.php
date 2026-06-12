<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository;

/**
 * Цель для кнопки inline open_app (поле web_app в MAX API).
 */
final class MaxOpenAppTargetResolver
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function resolveWebApp(): ?string
    {
        $explicit = trim((string) $this->config->get('max.ui_stand.mini_app_url', ''));

        if ($explicit !== '') {
            return $explicit;
        }

        $publicUrl = MaxAppRequestContext::publicAppUrl();

        if ($publicUrl !== null) {
            return $publicUrl.'/max-app';
        }

        $username = trim((string) $this->config->get('max.bot_username', ''));

        if ($username !== '') {
            return 'https://max.ru/'.$username;
        }

        return null;
    }

    public function resolveContactId(): ?int
    {
        $botUserId = (int) $this->config->get('max.bot_user_id', 0);

        return $botUserId > 0 ? $botUserId : null;
    }
}
