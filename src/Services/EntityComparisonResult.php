<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Services;

final readonly class EntityComparisonResult
{
    /**
     * @param  array<string, array<string, mixed>>  $new  New records to create, indexed by ISTAT code
     * @param  array<string, array{id: string, changes: array<string, array{old: mixed, new: mixed}>}>  $modified  Modified records with their changes
     * @param  array<string, array{id: string, name: mixed}>  $suppressed  Records to soft-delete
     */
    public function __construct(
        public array $new,
        public array $modified,
        public array $suppressed,
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
