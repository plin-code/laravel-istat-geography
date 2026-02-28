<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Models\Geography;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use PlinCode\IstatGeography\Database\Factories\MunicipalityFactory;

class Municipality extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'province_id',
        'istat_code',
    ];

    /**
     * Get the list of ISTAT fields that can be updated by the geography:update command.
     * These are fields that come from ISTAT data and can be safely overwritten.
     *
     * @return list<string>
     */
    public static function istatFields(): array
    {
        return [
            'name',
            'istat_code',
            'province_id',
        ];
    }

    protected static function newFactory(): MunicipalityFactory
    {
        return MunicipalityFactory::new();
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
