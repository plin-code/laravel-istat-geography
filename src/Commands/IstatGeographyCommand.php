<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PlinCode\IstatGeography\Services\GeographyImportService;

class IstatGeographyCommand extends Command
{
    protected $signature = 'geography:import';

    protected $description = 'Import regions, provinces and municipalities from ISTAT';

    public function __construct(
        private readonly GeographyImportService $importService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting geographical data import...');

        try {
            $count = DB::transaction(fn () => $this->importService->execute());

            $this->info("Import completed successfully! Imported {$count} municipalities.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during import: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
