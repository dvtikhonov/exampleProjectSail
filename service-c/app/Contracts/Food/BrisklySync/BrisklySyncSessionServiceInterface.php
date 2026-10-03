<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

use App\DTO\Food\BrisklySync\BrisklyCategoryDto;
use App\DTO\Food\BrisklySync\BrisklySyncApplyReportDto;
use App\DTO\Food\BrisklySync\BrisklySyncApprovalsDto;
use App\DTO\Food\BrisklySync\BrisklySyncSessionRecord;
use App\DTO\Food\BrisklySync\CreateBrisklySyncSessionDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\BrisklySync\SyncResultsDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Application-сервис жизненного цикла сессии Briskly sync.
 */
interface BrisklySyncSessionServiceInterface
{
    /**
     * Создаёт сессию и кладёт Bearer в token store.
     *
     * @throws FoodDomainException
     */
    public function createSession(CreateBrisklySyncSessionDto $dto): BrisklySyncSessionRecord;

    /**
     * Мета сессии без token.
     *
     * @throws FoodDomainException
     */
    public function getSession(string $sessionId): BrisklySyncSessionRecord;

    /**
     * Source-lines по фильтрам сессии (для MCP / UI).
     *
     * @return list<SourceMenuLineDto>
     *
     * @throws FoodDomainException
     */
    public function sourceLines(string $sessionId): array;

    /**
     * Загружает snapshot Briskly (без category filter).
     *
     * @throws FoodDomainException
     */
    public function loadSnapshot(string $sessionId): BrisklySyncSessionRecord;

    /**
     * Match через orchestrator + серверная классификация.
     *
     * @throws FoodDomainException
     */
    public function match(string $sessionId, bool $rematch = false): BrisklySyncSessionRecord;

    /**
     * Результаты ≤25+25 без token.
     *
     * @throws FoodDomainException
     */
    public function syncResults(string $sessionId): SyncResultsDto;

    /**
     * Сохраняет approvals (клиентский price игнорируется на уровне DTO).
     *
     * @throws FoodDomainException
     */
    public function updateApprovals(string $sessionId, BrisklySyncApprovalsDto $approvals): BrisklySyncSessionRecord;

    /**
     * Применяет отмеченные UPDATE/CREATE в Briskly.
     *
     * @throws FoodDomainException
     */
    public function apply(string $sessionId): BrisklySyncApplyReportDto;

    /**
     * Категории Briskly для CREATE (token из сессии).
     *
     * @return list<BrisklyCategoryDto>
     *
     * @throws FoodDomainException
     */
    public function listBrisklyCategories(string $sessionId): array;
}
