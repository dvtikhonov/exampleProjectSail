<?php

declare(strict_types=1);

namespace Tests\Feature\FoodReport;

use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use App\Contracts\Food\Order\FoodOrderItemSyncServiceInterface;
use App\Modules\FoodReport\Contracts\FoodReportMaxDeliveryInterface;
use App\Modules\FoodReport\Enums\ReportType;
use App\Modules\FoodReport\Jobs\ExportFoodReportToMaxJob;
use App\Repositories\Food\Order\FoodOrderMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxMessageDto;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: POST /api/food/admin/reports/export — очередь ExportFoodReportToMaxJob.
 */
class AdminFoodReportExportApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('max_food_order_items')) {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_09_10_000001_create_max_food_order_items_table.php',
            ]);
        }

        Artisan::call('migrate', [
            '--force' => true,
            '--path' => 'database/migrations/2026_09_11_000001_add_unique_order_dish_to_max_food_order_items_table.php',
        ]);

        $this->resetFoodDomainTables();
    }

    /** Export требует аутентификацию. */
    public function test_export_requires_authentication(): void
    {
        $this->postJson('/api/food/admin/reports/export', $this->payload([
            'report_type' => 'revenue',
        ]))->assertUnauthorized();
    }

    /** Без роли max_manager — 403. */
    public function test_export_forbidden_without_max_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();
        $restaurant = Restaurant::factory()->create();

        $this->postJson('/api/food/admin/reports/export', $this->payload([
            'restaurant_id' => $restaurant->id,
            'report_type' => 'revenue',
        ]), $auth['headers'])
            ->assertForbidden();
    }

    /** Валидация: report_type обязателен и только revenue|top_dishes. */
    public function test_export_validation_requires_report_type(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create();

        $this->postJson('/api/food/admin/reports/export', $this->payload([
            'restaurant_id' => $restaurant->id,
        ]), $manager['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['report_type']);

        $this->postJson('/api/food/admin/reports/export', $this->payload([
            'restaurant_id' => $restaurant->id,
            'report_type' => 'combined',
        ]), $manager['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['report_type']);
    }

    /** HTTP: dispatch job + 200 queued без вызова delivery. */
    public function test_export_queues_job_without_calling_delivery(): void
    {
        Bus::fake([ExportFoodReportToMaxJob::class]);

        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create();
        $filename = sprintf('report_%d_2026-09-01_2026-09-10.xlsx', $restaurant->id);

        $delivery = $this->createMock(FoodReportMaxDeliveryInterface::class);
        $delivery->expects($this->never())->method('deliver');
        $this->app->instance(FoodReportMaxDeliveryInterface::class, $delivery);

        $response = $this->postJson('/api/food/admin/reports/export', $this->payload([
            'restaurant_id' => $restaurant->id,
            'report_type' => 'revenue',
        ]), $manager['headers']);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'queued' => true,
                'filename' => $filename,
                'message' => 'Отчёт будет отправлен в чат MAX.',
            ]);

        Bus::assertDispatched(
            ExportFoodReportToMaxJob::class,
            function (ExportFoodReportToMaxJob $job) use ($manager, $restaurant, $filename): bool {
                return $job->maxUserId === $manager['user']->max_user_id
                    && $job->reportType === ReportType::Revenue
                    && $job->filter->restaurantId === $restaurant->id
                    && $job->filter->dateFrom === '2026-09-01'
                    && $job->filter->dateTo === '2026-09-10'
                    && $job->limitPerDay === 20
                    && $job->filename === $filename
                    && str_contains($job->messageText, 'Выручка за период');
            },
        );
    }

    /** Revenue .xlsx: job доставляет файл в MAX (sync queue). */
    public function test_export_revenue_xlsx_sent_to_max_user(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create(['name' => 'Export Cafe']);
        $capture = $this->bindCapturingMaxClient($manager['user']->max_user_id);

        $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-03',
            'items_total' => '400.00',
            'total' => '400.00',
            'delivery_cost' => '0.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-01',
            'items_total' => '1000.00',
            'total' => '1100.00',
            'delivery_cost' => '100.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-01',
            'items_total' => '500.00',
            'total' => '550.00',
            'delivery_cost' => '50.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-02',
            'items_total' => '200.00',
            'total' => '200.00',
            'delivery_cost' => '0.00',
        ]);
        $this->createOrder($restaurant, OrderStatus::PendingReview, [
            'delivery_date' => '2026-09-01',
            'items_total' => '9999.00',
        ]);

        $filename = sprintf('report_%d_2026-09-01_2026-09-03.xlsx', $restaurant->id);

        $response = $this->postJson('/api/food/admin/reports/export', $this->payload([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-03',
            'restaurant_id' => $restaurant->id,
            'report_type' => 'revenue',
        ]), $manager['headers']);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'queued' => true,
                'filename' => $filename,
                'message' => 'Отчёт будет отправлен в чат MAX.',
            ]);

        $this->assertSame($filename, $capture->fileName);
        $this->assertNotSame('', $capture->binary);
        $this->assertInstanceOf(MaxMessageDto::class, $capture->message);
        $this->assertSame($manager['user']->max_user_id, $capture->message->userId);
        $this->assertSame('file-token-test', $capture->message->fileAttachmentToken);
        $this->assertStringContainsString('Выручка за период', $capture->message->text);

        $sheet = $this->loadFirstSheet($capture->binary);
        $this->assertSame('Выручка', $sheet->getTitle());
        $this->assertSame('Дата', $sheet->getCell('A1')->getValue());
        $this->assertSame('Кол-во', $sheet->getCell('B1')->getValue());
        $this->assertSame('Средний чек', $sheet->getCell('C1')->getValue());
        $this->assertSame('Сумма', $sheet->getCell('D1')->getValue());
        $this->assertSame('2026-09-01', $sheet->getCell('A2')->getValue());
        $this->assertSame(2, (int) $sheet->getCell('B2')->getValue());
        $this->assertSame('750.00', (string) $sheet->getCell('C2')->getValue());
        $this->assertSame('1500.00', (string) $sheet->getCell('D2')->getValue());
        $this->assertSame('2026-09-02', $sheet->getCell('A3')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('B3')->getValue());
        $this->assertSame('200.00', (string) $sheet->getCell('C3')->getValue());
        $this->assertSame('200.00', (string) $sheet->getCell('D3')->getValue());
        $this->assertSame('2026-09-03', $sheet->getCell('A4')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('B4')->getValue());
        $this->assertSame('400.00', (string) $sheet->getCell('C4')->getValue());
        $this->assertSame('400.00', (string) $sheet->getCell('D4')->getValue());
        $this->assertSame('Итого', $sheet->getCell('A5')->getValue());
        $this->assertSame(4, (int) $sheet->getCell('B5')->getValue());
        $this->assertSame('525.00', (string) $sheet->getCell('C5')->getValue());
        $this->assertSame('2100.00', (string) $sheet->getCell('D5')->getValue());
    }

    /** Top dishes .xlsx: перекрёстная таблица, job отправляет в MAX. */
    public function test_export_top_dishes_xlsx_sent_to_max_user(): void
    {
        $manager = $this->maxManagerAuth();
        $restaurant = Restaurant::factory()->create(['name' => 'Top Export Cafe']);
        $capture = $this->bindCapturingMaxClient($manager['user']->max_user_id);
        $sync = app(FoodOrderItemSyncServiceInterface::class);
        $mapper = app(FoodOrderMapper::class);

        $dayOne = $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-02',
            'items_total' => '750.00',
            'items_snapshot' => [
                [
                    'dish_id' => 5,
                    'dish_name' => 'Борщ',
                    'unit_price' => '300.00',
                    'quantity' => 2,
                    'line_total' => '600.00',
                ],
                [
                    'dish_id' => 6,
                    'dish_name' => 'Салат',
                    'unit_price' => '150.00',
                    'quantity' => 1,
                    'line_total' => '150.00',
                ],
            ],
        ]);
        $sync->syncIfConfirmed($mapper->toRecord($dayOne));

        $dayTwo = $this->createOrder($restaurant, OrderStatus::Confirmed, [
            'delivery_date' => '2026-09-01',
            'items_total' => '300.00',
            'items_snapshot' => [
                [
                    'dish_id' => 5,
                    'dish_name' => 'Борщ',
                    'unit_price' => '300.00',
                    'quantity' => 1,
                    'line_total' => '300.00',
                ],
            ],
        ]);
        $sync->syncIfConfirmed($mapper->toRecord($dayTwo));

        $filename = sprintf('report_%d_2026-09-01_2026-09-02.xlsx', $restaurant->id);

        $response = $this->postJson('/api/food/admin/reports/export', $this->payload([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-02',
            'restaurant_id' => $restaurant->id,
            'report_type' => 'top_dishes',
        ]), $manager['headers']);

        $response->assertOk()
            ->assertJsonPath('filename', $filename)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('queued', true);

        $this->assertSame($filename, $capture->fileName);
        $this->assertStringContainsString('Топ позиций', $capture->message->text);

        $sheet = $this->loadFirstSheet($capture->binary);
        $this->assertSame('Топ позиций', $sheet->getTitle());
        $this->assertSame('Наименование блюд', $sheet->getCell('A1')->getValue());
        $this->assertSame('2026-09-01', $sheet->getCell('B1')->getValue());
        $this->assertSame('2026-09-02', $sheet->getCell('D1')->getValue());
        $this->assertSame('Кол-во', $sheet->getCell('B2')->getValue());
        $this->assertSame('Сумма', $sheet->getCell('C2')->getValue());
        $this->assertSame('Кол-во', $sheet->getCell('D2')->getValue());
        $this->assertSame('Сумма', $sheet->getCell('E2')->getValue());
        $this->assertSame('Борщ', $sheet->getCell('A3')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('B3')->getValue());
        $this->assertSame('300.00', (string) $sheet->getCell('C3')->getValue());
        $this->assertSame(2, (int) $sheet->getCell('D3')->getValue());
        $this->assertSame('600.00', (string) $sheet->getCell('E3')->getValue());
        $this->assertSame('Салат', $sheet->getCell('A4')->getValue());
        $this->assertSame('', (string) ($sheet->getCell('B4')->getValue() ?? ''));
        $this->assertSame(1, (int) $sheet->getCell('D4')->getValue());
        $this->assertSame('150.00', (string) $sheet->getCell('E4')->getValue());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOrder(Restaurant $restaurant, OrderStatus $status, array $overrides = []): FoodOrder
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 81_000 + FoodOrder::query()->count(),
            'first_name' => 'ExportUser',
        ]);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Экспорт, 1',
        ]);

        return FoodOrder::query()->create(array_merge([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUser->max_user_id,
            'restaurant_id' => $restaurant->id,
            'status' => $status,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '100.00',
            'items_total' => '100.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Экспорт, 1',
            'delivery_date' => '2026-09-01',
            'is_manual' => false,
        ], $overrides));
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return array<string, scalar|null>
     */
    private function payload(array $params = []): array
    {
        return array_merge([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-10',
            'restaurant_id' => 1,
        ], $params);
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerAuth(int $maxUserId = 20_020): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MaxManager',
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }

    /**
     * Мок клиента MAX: сохраняет binary/имя файла и DTO исходящего сообщения.
     *
     * @return object{binary: string, fileName: string, message: ?MaxMessageDto}
     */
    private function bindCapturingMaxClient(int $expectedUserId): object
    {
        $capture = (object) [
            'binary' => '',
            'fileName' => '',
            'message' => null,
        ];

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->once())
            ->method('uploadFile')
            ->willReturnCallback(static function (string $contents, string $fileName) use ($capture): string {
                $capture->binary = $contents;
                $capture->fileName = $fileName;

                return 'file-token-test';
            });
        $client->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(static function (MaxMessageDto $message) use ($capture, $expectedUserId): void {
                $capture->message = $message;
                self::assertSame($expectedUserId, $message->userId);
            });

        $this->app->instance(MaxMessengerClientInterface::class, $client);

        return $capture;
    }

    private function loadFirstSheet(string $binary): Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'food-report-export-test-');
        $this->assertNotFalse($path);
        $xlsxPath = $path.'.xlsx';
        $this->assertTrue(rename($path, $xlsxPath));
        file_put_contents($xlsxPath, $binary);

        try {
            $spreadsheet = IOFactory::load($xlsxPath);

            return $spreadsheet->getActiveSheet();
        } finally {
            @unlink($xlsxPath);
        }
    }
}
