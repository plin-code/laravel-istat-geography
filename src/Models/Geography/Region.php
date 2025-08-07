<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Models\Geography;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use PlinCode\IstatGeography\Database\Factories\RegionFactory;

class Region extends Model
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
        'istat_code',
    ];

    protected static function newFactory(): RegionFactory
    {
        return RegionFactory::new();
    }

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }
}
