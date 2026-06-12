<?php

namespace Tests\Unit\Shared\SalesOutletsDomain\Metadata;

use PHPUnit\Framework\TestCase;
use Shared\SalesOutletsDomain\Metadata\SalesOutletColumns;

final class SalesOutletColumnsTest extends TestCase
{
    public function test_searchable_db_columns_match_metadata_flags(): void
    {
        $this->assertSame(
            [
                'id',
                'shop',
                'manager',
                'curator',
                'name',
                'inn',
                'head_organization',
                'head_organization_type',
                'organization_name',
                'approved',
                'user_id',
            ],
            SalesOutletColumns::searchableDbColumns(),
        );

        $this->assertNotContains('status_label', SalesOutletColumns::searchableDbColumns());
    }

    public function test_sort_column_map_maps_status_label_to_status_column(): void
    {
        $map = SalesOutletColumns::sortColumnMap();

        $this->assertSame('status', $map['status_label']);
        $this->assertSame('shop', $map['shop']);
        $this->assertCount(count(SalesOutletColumns::all()), $map);
    }

    public function test_like_prefix_filter_column_map_excludes_user_id(): void
    {
        $map = SalesOutletColumns::likePrefixFilterColumnMap();

        $this->assertArrayHasKey('shop', $map);
        $this->assertArrayHasKey('inn', $map);
        $this->assertArrayNotHasKey('user_id', $map);
        $this->assertArrayNotHasKey('status_label', $map);
    }

    public function test_column_filter_type_map_includes_status_label_filter(): void
    {
        $map = SalesOutletColumns::columnFilterTypeMap();

        $this->assertSame(SalesOutletColumns::FILTER_STATUS_LABEL, $map['status_label']);
        $this->assertSame(SalesOutletColumns::FILTER_LIKE_PREFIX, $map['shop']);
        $this->assertArrayNotHasKey('user_id', $map);
    }

    public function test_keys_match_all_column_definitions(): void
    {
        $this->assertSame(
            array_column(SalesOutletColumns::all(), 'key'),
            SalesOutletColumns::keys(),
        );
    }
}
