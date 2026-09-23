<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PlinCode\IstatGeography\Services\CapImportService;
use PlinCode\IstatGeography\Services\CoordinatesImportService;
use PlinCode\IstatGeography\Services\GeographyImportService;

class IstatGeographyCommand extends Command
{
    protected $signature = 'geography:import
                            {--cap : Also import postal codes (CAP) from GeoJSON}
                            {--cap-only : Import only postal codes (CAP), skip ISTAT data}
                            {--cap-file= : Use a local JSON file for CAP data instead of downloading}
                            {--coordinates : Also import municipality coordinates (latitude and longitude)}
                            {--coordinates-only : Import only municipality coordinates, skip ISTAT data}
                            {--coordinates-file= : Use a local JSON (or gzipped JSON) file for coordinates instead of downloading}';

    protected $description = 'Import regions, provinces and municipalities from ISTAT';

    public function __construct(
        private readonly GeographyImportService $importService,
        private readonly CapImportService $capImportService,
        private readonly CoordinatesImportService $coordinatesImportService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $capOnly = $this->option('cap-only');
        $coordinatesOnly = $this->option('coordinates-only');
        $includeCap = $this->option('cap') || $capOnly;
        $includeCoordinates = $this->option('coordinates')
            || $coordinatesOnly
            || (config('istat-geography.coordinates.enabled') && ! $capOnly);

        try {
            return DB::transaction(function () use ($capOnly, $coordinatesOnly, $includeCap, $includeCoordinates): int {
                if (! $capOnly && ! $coordinatesOnly) {
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

                if ($includeCoordinates) {
                    $this->info('Importing municipality coordinates...');

                    $coordinatesFile = $this->option('coordinates-file');
                    if ($coordinatesFile) {
                        if (! file_exists($coordinatesFile)) {
                            throw new \RuntimeException("Coordinates file not found: {$coordinatesFile}");
                        }
                        $this->coordinatesImportService->useLocalFile($coordinatesFile);
                    }

                    $coordinatesCount = $this->coordinatesImportService->execute();
                    $this->info("Coordinates import completed! Updated {$coordinatesCount} municipalities.");
                }

                return self::SUCCESS;
            });
        } catch (\Exception $e) {
            $this->error('Error during import: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
