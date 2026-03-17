<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PlinCode\IstatGeography\Services\CapImportService;
use PlinCode\IstatGeography\Services\GeographyImportService;

class IstatGeographyCommand extends Command
{
    protected $signature = 'geography:import
                            {--cap : Also import postal codes (CAP) from GeoJSON}
                            {--cap-only : Import only postal codes (CAP), skip ISTAT data}
                            {--cap-file= : Use a local JSON file for CAP data instead of downloading}';

    protected $description = 'Import regions, provinces and municipalities from ISTAT';

    public function __construct(
        private readonly GeographyImportService $importService,
        private readonly CapImportService $capImportService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $capOnly = $this->option('cap-only');
        $includeCap = $this->option('cap') || $capOnly;

        try {
            return DB::transaction(function () use ($capOnly, $includeCap): int {
                if (! $capOnly) {
                    $this->info('Starting geographical data import...');
                    $count = $this->importService->execute();
                    $this->info("Import completed successfully! Imported {$count} municipalities.");
                }

                if ($includeCap) {
                    $this->info('Importing postal codes (CAP)...');

                    $capFile = $this->option('cap-file');
                    if ($capFile) {
                        if (! file_exists($capFile)) {
                            throw new \RuntimeException("CAP file not found: {$capFile}");
                        }
                        $this->capImportService->useLocalFile($capFile);
                    }

                    $capCount = $this->capImportService->execute();
                    $this->info("CAP import completed! Updated {$capCount} municipalities.");
                }

                return self::SUCCESS;
            });
        } catch (\Exception $e) {
            $this->error('Error during import: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
