<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

readonly class ParserCollectResultDto
{
    /**
     * @param  array<int, mixed>  $networkPayloads
     * @param  DomOrgHarvestDto[]  $domHarvest
     */
    public function __construct(
        public string $resolvedUrl,
        public bool $isDirectOrg,
        public ?string $directOrgId,
        public array $networkPayloads,
        public array $domHarvest,
        public PageMetaDto $pageMeta,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromParserArray(array $data): self
    {
        $domHarvest = [];

        foreach ((array) ($data['dom_harvest'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $domHarvest[] = DomOrgHarvestDto::fromParserArray($item);
        }

        /** @var array<string, mixed> $pageMeta */
        $pageMeta = (array) ($data['page_meta'] ?? []);

        return new self(
            resolvedUrl: (string) ($data['resolved_url'] ?? ''),
            isDirectOrg: (bool) ($data['is_direct_org'] ?? false),
            directOrgId: isset($data['direct_org_id']) ? (string) $data['direct_org_id'] : null,
            networkPayloads: array_values((array) ($data['network_payloads'] ?? [])),
            domHarvest: $domHarvest,
            pageMeta: PageMetaDto::fromParserArray($pageMeta),
        );
    }
}
