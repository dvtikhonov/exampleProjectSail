<?php

declare(strict_types=1);

namespace App\Services\Max\Food;

use App\DTO\Food\Chat\OrderMessageDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserDisplayDto;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Enums\Food\Review\OrderRejectionScope;
use App\Support\Food\Composition\OrderSnapshotComboResolver;
use Carbon\Carbon;
use Carbon\CarbonInterface;

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

    private const BUSINESS_TIMEZONE = 'Europe/Moscow';

    /**
     * Собирает текст уведомления о заказе с учётом лимита символов.
     */
    public function build(
        OrderDto $order,
        MaxUserDisplayDto $customer,
        int $maxTextLength = self::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        $header = $this->buildHeader($order, $customer);
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

    /**
     * Собирает заголовок MAX-сообщения о заказе.
     */
    private function buildHeader(OrderDto $order, MaxUserDisplayDto $customer): string
    {
        $lines = [
            sprintf('Новая заявка №%d', $order->id),
            sprintf('Ресторан: %s', $order->restaurantName),
            sprintf('Клиент: %s', $this->formatClient($customer)),
        ];

        $address = trim((string) $order->deliveryAddress);

        if ($address !== '') {
            $lines[] = sprintf('Адрес: %s', $address);
        }

        $deliveryDateLabel = $this->formatDeliveryDateLabel($order->deliveryDate);

        if ($deliveryDateLabel !== null) {
            $lines[] = sprintf('Дата доставки: %s', $deliveryDateLabel);
        }

        return implode("\n", $lines);
    }

    /**
     * Короткое уведомление клиенту о новом сообщении в чате заказа (без текста сообщения).
     */
    public function buildOrderChatCustomerNotification(FoodOrderRecord $order): string
    {
        return sprintf('В чат заказа №%d поступило сообщение', $order->id);
    }

    /**
     * Уведомление в MAX_UI_STAND_* о новом сообщении в чате заказа (с текстом сообщения).
     */
    public function buildOrderChatUiStandNotification(FoodOrderRecord $order, OrderMessageDto $message): string
    {
        return implode("\n", [
            sprintf('В чат заказа №%d поступило сообщение', $order->id),
            $this->truncateChatPreview($message->body),
        ]);
    }

    /**
     * Payload кнопки open_app → start_param mini-app (только [A-Za-z0-9_-]).
     *
     * @see https://dev.max.ru/docs/webapps/introduction
     */
    public function buildOrderChatStartParam(int $orderId): string
    {
        return sprintf('order_%d_chat', $orderId);
    }

    /**
     * URL mini-app с query deep-link (локальный браузер / fallback).
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
     * Текст уведомления клиенту о принятии заказа на рассмотрение.
     */
    public function buildCustomerSubmitted(FoodOrderRecord $order): string
    {
        return sprintf(
            'Заказ №%d принят на рассмотрение. В чате заказа можете сделать уточнения к заказу',
            $order->id,
        );
    }

    /**
     * Текст уведомления клиенту о подтверждении заявки.
     */
    public function buildCustomerConfirmed(FoodOrderRecord $order): string
    {
        return sprintf('Заявка №%d принята к исполнению', $order->id);
    }

    /**
     * Текст доп. уведомления менеджеру, оформившему ручной заказ, после подтверждения.
     *
     * Формат:
     * Заказ на 21.07. от Иван Иванов:
     * 1. Салат "...", 110г – 97р - 2шт.
     * 2. Блюдо 1 / Блюдо 2, 130г / 150г – 160р - 1шт.
     */
    public function buildManualOrderCreatorConfirmed(
        FoodOrderRecord $order,
        int $maxTextLength = self::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        $header = sprintf(
            'Заказ на %s. от %s:',
            $this->formatOrderDate($order->deliveryDate ?? $order->createdAt),
            $this->formatCustomerDisplayNameFromRecord($order),
        );

        $itemsSnapshot = $order->itemsSnapshot;
        $itemLines = $this->formatManualOrderItemLines($itemsSnapshot);

        if ($itemLines === []) {
            return $this->ensureWithinLimit($header, $maxTextLength);
        }

        $fullText = $header."\n".implode("\n", $itemLines);

        if (mb_strlen($fullText) <= $maxTextLength) {
            return $fullText;
        }

        $totalLines = count($itemLines);

        for ($includedCount = $totalLines - 1; $includedCount >= 0; $includedCount--) {
            $remaining = $totalLines - $includedCount;
            $lines = array_slice($itemLines, 0, $includedCount);
            $lines[] = $this->buildTruncationSuffix($remaining);
            $candidate = $header."\n".implode("\n", $lines);

            if (mb_strlen($candidate) <= $maxTextLength) {
                return $candidate;
            }
        }

        return $this->ensureWithinLimit(
            $header."\n".$this->buildTruncationSuffix($totalLines),
            $maxTextLength,
        );
    }

    /**
     * Текст уведомления клиенту об отклонении заявки.
     */
    public function buildCustomerRejected(FoodOrderRecord $order, OrderRejectionScope $scope): string
    {
        $comment = match ($scope) {
            OrderRejectionScope::Address => trim((string) ($order->addressRejectionComment ?? '')),
            OrderRejectionScope::Composition => trim((string) ($order->compositionRejectionComment ?? '')),
            OrderRejectionScope::Payment => trim((string) ($order->paymentRejectionComment ?? '')),
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

    /**
     * Текст уведомления клиенту об окончательном варианте заказа после правки состава.
     */
    public function buildCustomerCompositionChanged(
        FoodOrderRecord $order,
        int $maxTextLength = self::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        $headerLines = [
            'Заказ изменен по вашему согласованию',
            sprintf('Заказ №%d', $order->id),
            sprintf('Ресторан: %s', (string) ($order->restaurantName ?? '')),
        ];

        $address = trim((string) ($order->deliveryAddress ?? ''));

        if ($address !== '') {
            $headerLines[] = sprintf('Адрес: %s', $address);
        }

        $deliveryDateLabel = $this->formatDeliveryDateLabel($order->deliveryDate);

        if ($deliveryDateLabel !== null) {
            $headerLines[] = sprintf('Дата доставки: %s', $deliveryDateLabel);
        }

        $header = implode("\n", $headerLines);

        $footerLines = [
            sprintf('Сумма блюд: %s ₽', $this->formatMoneyAmount($order->itemsTotal)),
        ];

        if ($order->deliveryCost !== null) {
            $footerLines[] = sprintf('Доставка: %s ₽', $this->formatMoneyAmount($order->deliveryCost));
        }

        $footerLines[] = sprintf('Итого: %s ₽', $this->formatMoneyAmount($order->total));
        $footer = implode("\n", $footerLines);

        $items = $this->extractItemsFromSnapshot($order->itemsSnapshot);

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

    /**
     * Собирает подвал MAX-сообщения о заказе.
     */
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

    /**
     * Обрезает превью текста чата до лимита.
     */
    private function truncateChatPreview(string $body): string
    {
        $normalized = trim($body);

        if (mb_strlen($normalized) <= self::ORDER_CHAT_PREVIEW_MAX_LENGTH) {
            return $normalized;
        }

        return mb_substr($normalized, 0, self::ORDER_CHAT_PREVIEW_MAX_LENGTH - 1).'…';
    }

    /**
     * Форматирует данные клиента для сообщения.
     */
    private function formatClient(MaxUserDisplayDto $customer): string
    {
        $name = trim(implode(' ', array_filter([
            $customer->firstName,
            $customer->lastName,
        ])));

        $details = [];

        if ($customer->username !== null && trim($customer->username) !== '') {
            $details[] = '@'.trim($customer->username);
        }

        $details[] = 'id '.$customer->maxUserId;

        $detailsText = implode(', ', $details);

        if ($name !== '') {
            return $name.' ('.$detailsText.')';
        }

        return $detailsText;
    }

    /**
     * Отображаемое имя клиента из проекции заказа.
     */
    private function formatCustomerDisplayNameFromRecord(FoodOrderRecord $order): string
    {
        $name = trim(implode(' ', array_filter([
            $order->customerFirstName,
            $order->customerLastName,
        ])));

        if ($name !== '') {
            return $name;
        }

        if ($order->customerUsername !== null && trim($order->customerUsername) !== '') {
            return '@'.trim($order->customerUsername);
        }

        return 'id '.$order->maxUserId;
    }

    /**
     * Дата заказа в формате дд.мм для уведомления менеджеру.
     */
    private function formatOrderDate(mixed $createdAt): string
    {
        if ($createdAt instanceof CarbonInterface) {
            return $createdAt->timezone(self::BUSINESS_TIMEZONE)->format('d.m');
        }

        if (is_string($createdAt) && trim($createdAt) !== '') {
            return Carbon::parse($createdAt)
                ->timezone(self::BUSINESS_TIMEZONE)
                ->format('d.m');
        }

        return Carbon::now(self::BUSINESS_TIMEZONE)->format('d.m');
    }

    /**
     * Дата доставки (Y-m-d) в формате дд.мм.гггг для текста уведомления.
     */
    private function formatDeliveryDateLabel(?string $deliveryDate): ?string
    {
        if ($deliveryDate === null || trim($deliveryDate) === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($deliveryDate), $matches) !== 1) {
            return null;
        }

        return sprintf('%s.%s.%s', $matches[3], $matches[2], $matches[1]);
    }

    /**
     * Строки позиций ручного заказа (обычные и комбо) с нумерацией.
     *
     * @param  list<mixed>|array<int, mixed>  $itemsSnapshot
     * @return list<string>
     */
    private function formatManualOrderItemLines(array $itemsSnapshot): array
    {
        $items = $this->extractItemsFromSnapshot($itemsSnapshot);
        $groups = $this->comboResolver->groupSnapshotItems($items);
        $lines = [];
        $number = 1;

        foreach ($groups as $group) {
            if (($group['type'] ?? '') === 'combo') {
                $line = $this->formatManualComboLine($number, $group['items'], (int) ($group['quantity'] ?? 0));
            } else {
                $item = $group['items'][0] ?? null;

                if (! is_array($item)) {
                    continue;
                }

                $line = $this->formatManualSingleItemLine($number, $item);
            }

            if ($line === null) {
                continue;
            }

            $lines[] = $line;
            $number++;
        }

        return $lines;
    }

    /**
     * Форматирует обычную позицию ручного заказа.
     *
     * @param  array<string, mixed>  $item
     */
    private function formatManualSingleItemLine(int $number, array $item): ?string
    {
        $name = trim((string) ($item['dish_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $parts = [sprintf('%d. %s', $number, $name)];

        $description = trim((string) ($item['description'] ?? ''));

        if ($description !== '') {
            $parts[0] .= sprintf(' (%s)', $description);
        }

        $weightLabel = $this->formatWeightLabel($item);

        if ($weightLabel !== null) {
            $parts[0] .= ', '.$weightLabel;
        }

        $parts[0] .= sprintf(
            ' – %sр - %dшт.',
            $this->formatRublesAmount($item['unit_price'] ?? null),
            (int) ($item['quantity'] ?? 0),
        );

        return $parts[0];
    }

    /**
     * Форматирует комбо-позицию: «блюдо 1 / блюдо 2, вес1 / вес2 – суммар - Nшт.»
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function formatManualComboLine(int $number, array $items, int $quantity): ?string
    {
        if ($items === []) {
            return null;
        }

        $names = [];
        $weights = [];
        $unitPriceSum = 0.0;

        foreach ($items as $item) {
            $name = trim((string) ($item['dish_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $names[] = $name;
            $weights[] = $this->formatWeightLabel($item) ?? '';
            $unitPriceSum += (float) ($item['unit_price'] ?? 0);
        }

        if ($names === []) {
            return null;
        }

        $line = sprintf('%d. %s', $number, implode(' / ', $names));

        if ($this->hasAnyNonEmptyWeight($weights)) {
            $line .= ', '.implode(' / ', $weights);
        }

        $line .= sprintf(
            ' – %sр - %dшт.',
            $this->formatRublesAmount($unitPriceSum),
            $quantity,
        );

        return $line;
    }

    /**
     * Проверяет, есть ли хотя бы один непустой вес в комбо.
     *
     * @param  list<string>  $weights
     */
    private function hasAnyNonEmptyWeight(array $weights): bool
    {
        foreach ($weights as $weight) {
            if ($weight !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Форматирует вес позиции: «110г».
     *
     * @param  array<string, mixed>  $item
     */
    private function formatWeightLabel(array $item): ?string
    {
        $weight = $item['weight'] ?? null;

        if ($weight === null || $weight === '') {
            return null;
        }

        $unitValue = (string) ($item['weight_unit'] ?? DishWeightUnit::Gram->value);
        $unit = DishWeightUnit::tryFrom($unitValue) ?? DishWeightUnit::Gram;

        return sprintf('%s%s', (string) (int) round((float) $weight), $unit->label());
    }

    /**
     * Форматирует цену в рублях без копеек для уведомления менеджеру.
     */
    private function formatRublesAmount(mixed $amount): string
    {
        return (string) (int) round((float) ($amount ?? 0));
    }

    /**
     * Извлекает позиции из снимка состава заказа.
     *
     * @return list<array<string, mixed>>
     */
    private function extractItems(OrderDto $order): array
    {
        return $this->extractItemsFromSnapshot($order->itemsSnapshot);
    }

    /**
     * Извлекает позиции из массива items_snapshot.
     *
     * @param  list<mixed>|array<int, mixed>  $itemsSnapshot
     * @return list<array<string, mixed>>
     */
    private function extractItemsFromSnapshot(array $itemsSnapshot): array
    {
        $items = [];

        foreach ($itemsSnapshot as $snapshot) {
            if (! is_array($snapshot)) {
                continue;
            }

            $item = [
                'dish_id' => (int) ($snapshot['dish_id'] ?? 0),
                'dish_name' => (string) ($snapshot['dish_name'] ?? ''),
                'description' => isset($snapshot['description']) ? (string) $snapshot['description'] : null,
                'weight' => $snapshot['weight'] ?? null,
                'weight_unit' => $snapshot['weight_unit'] ?? null,
                'quantity' => (int) ($snapshot['quantity'] ?? 0),
                'unit_price' => (string) ($snapshot['unit_price'] ?? '0.00'),
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
     * Форматирует денежную сумму для клиентского уведомления.
     */
    private function formatMoneyAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Собирает секцию позиций заказа для сообщения.
     *
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
     * Форматирует строки позиций заказа.
     *
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

    /**
     * Возвращает суффикс обрезки длинного сообщения.
     */
    private function buildTruncationSuffix(int $remainingCount): string
    {
        return sprintf(self::TRUNCATION_SUFFIX_TEMPLATE, $remainingCount);
    }

    /**
     * Склеивает части MAX-сообщения о заказе.
     */
    private function assembleMessage(string $header, string $itemsSection, string $footer): string
    {
        $sections = [$header];

        if ($itemsSection !== '') {
            $sections[] = $itemsSection;
        }

        $sections[] = $footer;

        return implode("\n\n", $sections);
    }

    /**
     * Укладывает текст сообщения в лимит длины.
     */
    private function ensureWithinLimit(string $text, int $maxTextLength): string
    {
        if (mb_strlen($text) <= $maxTextLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxTextLength);
    }
}
