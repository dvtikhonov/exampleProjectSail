<?php

declare(strict_types=1);

namespace App\Models\Food;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent-модель сессии Briskly sync (таблица briskly_sync_sessions).
 */
class BrisklySyncSession extends Model
{
    protected $table = 'briskly_sync_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'restaurant_id',
        'created_by_max_user_id',
        'vps_category_id',
        'search_text',
        'clarification',
        'status',
        'briskly_snapshot',
        'source_lines_snapshot',
        'proposals',
        'approvals',
        'apply_report',
        'allowed_briskly_category_ids',
        'source_price_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'restaurant_id' => 'integer',
            'created_by_max_user_id' => 'integer',
            'vps_category_id' => 'integer',
            'briskly_snapshot' => 'array',
            'source_lines_snapshot' => 'array',
            'proposals' => 'array',
            'approvals' => 'array',
            'apply_report' => 'array',
            'allowed_briskly_category_ids' => 'array',
        ];
    }
}
