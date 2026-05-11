<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography;

use PlinCode\IstatGeography\Services\GeographyImportService;

readonly class IstatGeography
{
    public function __construct(
        private GeographyImportService $importService
    ) {}

    public function import(): int
    {
        return $this->importService->execute();
    }
}
