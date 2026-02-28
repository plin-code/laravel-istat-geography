<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Models\Geography;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use PlinCode\IstatGeography\Database\Factories\ProvinceFactory;

class Province extends Model
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
        'code',
        'region_id',
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
            'code',
            'istat_code',
            'region_id',
        ];
    }

    protected static function newFactory(): ProvinceFactory
    {
        return ProvinceFactory::new();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class);
    }
}
