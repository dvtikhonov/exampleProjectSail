<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Jobs;

use App\Modules\FoodReport\Contracts\FoodReportMaxDeliveryInterface;
use App\Modules\FoodReport\Contracts\FoodReportSpreadsheetExporterInterface;
use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\Enums\ReportType;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Асинхронный экспорт Food Report в .xlsx и доставка файла в чат MAX.
 *
 * Уникальность по параметрам выгрузки — защита от двойного клика (два файла).
 */
class ExportFoodReportToMaxJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Максимум попыток при сбое экспорта/доставки. */
    public int $tries = 3;

    /** Таймаут одной попытки (секунды). */
    public int $timeout = 120;

    /** Сколько секунд держать unique-lock (двойной клик). */
    public int $uniqueFor = 60;

    /**
     * @param  int  $maxUserId  получатель в MAX
     * @param  ReportType  $reportType  тип отчёта
     * @param  ReportFilterDto  $filter  фильтр периода/ресторана
     * @param  int  $limitPerDay  лимит топ-позиций на день
     * @param  string  $filename  имя .xlsx вложения
     * @param  string  $messageText  подпись к сообщению в MAX
     */
    public function __construct(
        public readonly int $maxUserId,
        public readonly ReportType $reportType,
        public readonly ReportFilterDto $filter,
        public readonly int $limitPerDay,
        public readonly string $filename,
        public readonly string $messageText,
    ) {}

    /**
     * Задержки между повторными попытками (секунды).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Ключ уникальности: один набор параметров выгрузки на пользователя.
     */
    public function uniqueId(): string
    {
        return hash('sha256', implode('|', [
            (string) $this->maxUserId,
            (string) $this->filter->restaurantId,
            $this->filter->dateFrom,
            $this->filter->dateTo,
            $this->filter->dateAxis->value,
            $this->reportType->value,
            (string) $this->limitPerDay,
        ]));
    }

    /**
     * Генерирует .xlsx и отправляет файл пользователю MAX.
     */
    public function handle(
        FoodReportSpreadsheetExporterInterface $exporter,
        FoodReportMaxDeliveryInterface $delivery,
    ): void {
        $binary = $exporter->export(
            $this->reportType,
            $this->filter,
            $this->limitPerDay,
        );

        $delivery->deliver(
            $this->maxUserId,
            $binary,
            $this->filename,
            $this->messageText,
        );
    }
}
