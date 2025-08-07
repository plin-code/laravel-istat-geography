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

    protected static function newFactory(): MunicipalityFactory
    {
        return MunicipalityFactory::new();
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
