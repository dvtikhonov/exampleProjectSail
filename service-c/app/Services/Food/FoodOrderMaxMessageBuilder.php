<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\DTO\Food\OrderDto;
use App\DTO\Food\OrderMessageDto;
use App\Enums\Food\OrderRejectionScope;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use App\Support\OrderSnapshotComboResolver;

/**
 * Формирование текста уведомления о заказе для MAX с усечением по лимиту.
 */
class FoodOrderMaxMessageBuilder
{
    public function __construct(
        private readonly OrderSnapshotComboResolver $comboResolver,
    ) {}

    private const DEFAULT_MAX_TEXT_LENGTH = 4000;

    private const TRUNCATION_SUFFIX_TEMPLATE = '…и ещё %d позиций';

    private const ORDER_CHAT_PREVIEW_MAX_LENGTH = 200;

    /**
     * Собирает текст уведомления о заказе с учётом лимита символов.
     */
    public function build(
        OrderDto $order,
        MaxUser $maxUser,
        int $maxTextLength = self::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        $header = $this->buildHeader($order, $maxUser);
        $footer = $this->buildFooter($order);
        $items = $this->extractItems($order);

        if ($items === []) {
            return $this->ensureWithinLimit($this->assembleMessage($header, '', $footer), $maxTextLength);
        }

        $fullItemsSection = implode("\n", $this->formatItemsLines($items));
        $fullText = $this->assembleMessage($header, $fullItemsSection, $footer);

        if (mb_strlen($fullText) <= $maxTextLength) {
            return $fullText;
        }

        $totalItems = count($items);

        for ($includedCount = $totalItems - 1; $includedCount >= 0; $includedCount--) {
            $remaining = $totalItems - $includedCount;
            $itemsSection = $this->buildItemsSection($items, $includedCount, $remaining);
            $candidate = $this->assembleMessage($header, $itemsSection, $footer);

            if (mb_strlen($candidate) <= $maxTextLength) {
                return $candidate;
            }
        }

        return $this->ensureWithinLimit(
            $this->assembleMessage($header, $this->buildTruncationSuffix($totalItems), $footer),
            $maxTextLength,
        );
    }

    private function buildHeader(OrderDto $order, MaxUser $maxUser): string
    {
        $lines = [
            sprintf('Новая заявка №%d', $order->id),
            sprintf('Ресторан: %s', $order->restaurantName),
            sprintf('Клиент: %s', $this->formatClient($maxUser)),
        ];

        $address = trim((string) $order->deliveryAddress);

        if ($address !== '') {
            $lines[] = sprintf('Адрес: %s', $address);
        }

        return implode("\n", $lines);
    }

    /**
     * Текст push-уведомления о новом сообщении в чате заказа.
     */
    public function buildOrderChatNotification(FoodOrder $order, OrderMessageDto $message): string
    {
        return implode("\n", [
            sprintf('Новое сообщение по заказу №%d', $order->id),
            sprintf('%s: %s', $this->formatMessageSender($message), $this->truncateChatPreview($message->body)),
        ]);
    }

    /**
     * URL mini-app с deep-link на чат заказа.
     */
    public function buildOrderChatOpenAppUrl(int $orderId, ?string $baseWebAppUrl): ?string
    {
        $baseUrl = trim((string) $baseWebAppUrl);

        if ($baseUrl === '') {
            return null;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query([
            'order_id' => $orderId,
            'view' => 'chat',
        ]);
    }

    /**
     * Текст уведомления клиенту о подтверждении заявки.
     */
    public function buildCustomerConfirmed(FoodOrder $order): string
    {
        return sprintf('Заявка №%d принята к исполнению', $order->id);
    }

    /**
     * Текст уведомления клиенту об отклонении заявки.
     */
    public function buildCustomerRejected(FoodOrder $order, OrderRejectionScope $scope): string
    {
        $comment = match ($scope) {
            OrderRejectionScope::Address => trim((string) $order->address_rejection_comment),
            OrderRejectionScope::Composition => trim((string) $order->composition_rejection_comment),
            OrderRejectionScope::Payment => trim((string) $order->payment_rejection_comment),
        };

        $lines = [
            sprintf('Заявка №%d отклонена', $order->id),
            sprintf('Проверка: %s', $scope->label()),
        ];

        if ($comment !== '') {
            $lines[] = sprintf('Причина: %s', $comment);
        }

        return implode("\n", $lines);
    }

    private function buildFooter(OrderDto $order): string
    {
        $lines = [
            'Статус: ожидает проверки адреса, состава и оплаты',
            sprintf('Сумма блюд: %s ₽', $order->itemsTotal),
        ];

        if ($order->deliveryApplicable) {
            $lines[] = sprintf('Доставка: %s ₽', $order->deliveryCost ?? '0.00');
        }

        $lines[] = sprintf('Итого: %s ₽', $order->total);

        return implode("\n", $lines);
    }

    private function formatMessageSender(OrderMessageDto $message): string
    {
        $name = trim(implode(' ', array_filter([
            $message->senderFirstName,
            $message->senderLastName,
        ])));

        if ($name !== '') {
            return $name;
        }

        if ($message->senderUsername !== null && trim($message->senderUsername) !== '') {
            return '@'.trim($message->senderUsername);
        }

        return 'Пользователь';
    }

    private function truncateChatPreview(string $body): string
    {
        $normalized = trim($body);

        if (mb_strlen($normalized) <= self::ORDER_CHAT_PREVIEW_MAX_LENGTH) {
            return $normalized;
        }

        return mb_substr($normalized, 0, self::ORDER_CHAT_PREVIEW_MAX_LENGTH - 1).'…';
    }

    private function formatClient(MaxUser $maxUser): string
    {
        $name = trim(implode(' ', array_filter([
            $maxUser->first_name,
            $maxUser->last_name,
        ])));

        $details = [];

        if ($maxUser->username !== null && trim($maxUser->username) !== '') {
            $details[] = '@'.trim($maxUser->username);
        }

        $details[] = 'id '.$maxUser->max_user_id;

        $detailsText = implode(', ', $details);

        if ($name !== '') {
            return $name.' ('.$detailsText.')';
        }

        return $detailsText;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractItems(OrderDto $order): array
    {
        $items = [];

        foreach ($order->itemsSnapshot as $snapshot) {
            if (! is_array($snapshot)) {
                continue;
            }

            $item = [
                'dish_id' => (int) ($snapshot['dish_id'] ?? 0),
                'dish_name' => (string) ($snapshot['dish_name'] ?? ''),
                'quantity' => (int) ($snapshot['quantity'] ?? 0),
                'line_total' => (string) ($snapshot['line_total'] ?? '0.00'),
            ];

            if (isset($snapshot['combo_ref']) && $snapshot['combo_ref'] !== null && $snapshot['combo_ref'] !== '') {
                $item['combo_ref'] = (string) $snapshot['combo_ref'];
                $item['combo_partner_dish_ids'] = is_array($snapshot['combo_partner_dish_ids'] ?? null)
                    ? $snapshot['combo_partner_dish_ids']
                    : [];
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  list<array{dish_name: string, quantity: int, line_total: string}>  $items
     */
    private function buildItemsSection(array $items, int $includedCount, int $remaining): string
    {
        $lines = [];

        if ($includedCount > 0) {
            $lines = $this->formatItemsLines(array_slice($items, 0, $includedCount));
        }

        if ($remaining > 0) {
            $lines[] = $this->buildTruncationSuffix($remaining);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    private function formatItemsLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = sprintf(
                '• %s × %d — %s ₽',
                (string) $item['dish_name'],
                (int) $item['quantity'],
                (string) $item['line_total'],
            );

            $comboLabel = $this->comboResolver->formatComboLabel($item, $items);

            if ($comboLabel !== null) {
                $lines[] = '  '.$comboLabel;
            }
        }

        return $lines;
    }

    private function buildTruncationSuffix(int $remainingCount): string
    {
        return sprintf(self::TRUNCATION_SUFFIX_TEMPLATE, $remainingCount);
    }

    private function assembleMessage(string $header, string $itemsSection, string $footer): string
    {
        $sections = [$header];

        if ($itemsSection !== '') {
            $sections[] = $itemsSection;
        }

        $sections[] = $footer;

        return implode("\n\n", $sections);
    }

    private function ensureWithinLimit(string $text, int $maxTextLength): string
    {
        if (mb_strlen($text) <= $maxTextLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxTextLength);
    }
}
