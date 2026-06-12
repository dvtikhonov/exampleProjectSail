<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Max\UiStand\MaxUiStandGreetingSender;
use App\Support\MaxOpenAppTargetResolver;
use Illuminate\Console\Command;
use RuntimeException;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

class MaxUiStandSendCommand extends Command
{
    protected $signature = 'max:ui-stand:send';

    protected $description = 'Отправить приветствие стенда MAX с inline-клавиатурой всем получателям из конфига';

    public function handle(MaxUiStandGreetingSender $sender, MaxOpenAppTargetResolver $openAppTargetResolver): int
    {
        $openAppTarget = $openAppTargetResolver->resolveWebApp();

        if ($openAppTarget === null) {
            $this->warn('Кнопка open_app не будет в сообщении: задайте MAX_MINI_APP_URL или MAX_WEBHOOK_URL.');
            $this->warn('Выполните max:bot:info и обновите .env.');
        } else {
            $this->line('Кнопка «Заказ еды» → web_app: '.$openAppTarget);
            $contactId = $openAppTargetResolver->resolveContactId();
            if ($contactId !== null) {
                $this->line('  contact_id: '.$contactId);
            } else {
                $this->warn('  MAX_BOT_USER_ID не задан — добавьте user_id из max:bot:info для надёжности на desktop.');
            }
        }

        try {
            $sender->send();
            $this->info('Приветствие отправлено всем настроенным получателям.');
            $this->line('Важно: нажимайте «Заказ еды» только в этом новом сообщении (старые кнопки не обновляются).');

            return self::SUCCESS;
        } catch (RuntimeException|MaxMessengerException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Не удалось отправить приветствие стенда MAX.');

            return self::FAILURE;
        }
    }
}
