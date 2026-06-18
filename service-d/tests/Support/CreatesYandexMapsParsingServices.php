<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\YandexMaps\Parsing\DomHarvestMapper;
use App\Services\YandexMaps\Parsing\JsonTreeWalker;
use App\Services\YandexMaps\Parsing\OrganizationCandidateBuilder;
use App\Services\YandexMaps\Parsing\OrganizationCandidateMerger;
use App\Services\YandexMaps\Parsing\OrganizationRecordMapper;
use App\Services\YandexMaps\Parsing\YandexUrlHelper;

/**
 * Фабрика сервисов парсинга Yandex Maps для unit-тестов.
 */
trait CreatesYandexMapsParsingServices
{
    protected function createJsonTreeWalker(): JsonTreeWalker
    {
        return new JsonTreeWalker;
    }

    protected function createYandexUrlHelper(): YandexUrlHelper
    {
        return new YandexUrlHelper;
    }

    protected function createOrganizationRecordMapper(): OrganizationRecordMapper
    {
        return new OrganizationRecordMapper(
            $this->createJsonTreeWalker(),
            $this->createYandexUrlHelper(),
        );
    }

    protected function createOrganizationCandidateMerger(): OrganizationCandidateMerger
    {
        return new OrganizationCandidateMerger($this->createOrganizationRecordMapper());
    }

    protected function createDomHarvestMapper(): DomHarvestMapper
    {
        return new DomHarvestMapper(
            $this->createYandexUrlHelper(),
            $this->createOrganizationRecordMapper(),
        );
    }

    protected function createOrganizationCandidateBuilder(): OrganizationCandidateBuilder
    {
        $walker = $this->createJsonTreeWalker();
        $urlHelper = $this->createYandexUrlHelper();
        $recordMapper = new OrganizationRecordMapper($walker, $urlHelper);

        return new OrganizationCandidateBuilder(
            $walker,
            $urlHelper,
            $recordMapper,
            new DomHarvestMapper($urlHelper, $recordMapper),
            new OrganizationCandidateMerger($recordMapper),
        );
    }
}
