<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Services;

final readonly class EntityComparisonResult
{
    /**
     * @param  array<string, array<string, mixed>>  $new  New records to create, indexed by ISTAT code
     * @param  array<string, array{id: string, changes: array<string, array{old: mixed, new: mixed}>, province_istat_code?: string}>  $modified  Modified records with their changes; province_istat_code is set when the province is created by the same update
     * @param  array<string, array{id: string, name: mixed}>  $suppressed  Records to soft-delete
     * @param  array<string, array{id: string|null, name: mixed, from: string|null, to: string}>  $skipped  ISTAT codes left out because they are already taken, indexed by that code; from is null for a new municipality, otherwise the code the existing record keeps
     */
    public function __construct(
        public array $new,
        public array $modified,
        public array $suppressed,
        public array $skipped = [],
    ) {}

    public function countNew(): int
    {
        return count($this->new);
    }

    public function countModified(): int
    {
        return count($this->modified);
    }

    public function countSuppressed(): int
    {
        return count($this->suppressed);
    }

    public function hasChanges(): bool
    {
        return $this->countNew() > 0
            || $this->countModified() > 0
            || $this->countSuppressed() > 0;
    }
}
