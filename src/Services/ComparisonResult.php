<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Services;

final readonly class ComparisonResult
{
    public function __construct(
        public EntityComparisonResult $regions,
        public EntityComparisonResult $provinces,
        public EntityComparisonResult $municipalities,
    ) {}

    public function totalNew(): int
    {
        return $this->regions->countNew()
            + $this->provinces->countNew()
            + $this->municipalities->countNew();
    }

    public function totalModified(): int
    {
        return $this->regions->countModified()
            + $this->provinces->countModified()
            + $this->municipalities->countModified();
    }

    public function totalSuppressed(): int
    {
        return $this->regions->countSuppressed()
            + $this->provinces->countSuppressed()
            + $this->municipalities->countSuppressed();
    }

    public function hasChanges(): bool
    {
        return $this->totalNew() > 0
            || $this->totalModified() > 0
            || $this->totalSuppressed() > 0;
    }
}
