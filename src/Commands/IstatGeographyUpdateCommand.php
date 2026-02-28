<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Commands;

use Illuminate\Console\Command;
use PlinCode\IstatGeography\Services\ComparisonResult;
use PlinCode\IstatGeography\Services\GeographyCompareService;
use PlinCode\IstatGeography\Services\GeographyUpdateService;

class IstatGeographyUpdateCommand extends Command
{
    protected $signature = 'geography:update
                            {--dry-run : Simulate the update without making changes}';

    protected $description = 'Update geographical data by synchronizing with the latest ISTAT data (adds new records, updates existing ones, soft-deletes removed ones)';

    public function handle(
        GeographyCompareService $compareService,
        GeographyUpdateService $updateService
    ): int {
        $isDryRun = (bool) $this->option('dry-run');
        $prefix = $isDryRun ? '[DRY-RUN] ' : '';

        $this->info($prefix.'Starting geographical data update...');

        if ($this->output->isVerbose()) {
            $this->line($prefix.'Downloading ISTAT data...');
        }

        $comparison = $compareService->compare();

        if ($this->output->isVeryVerbose()) {
            $this->line($prefix.'Comparing with existing database records...');
        }

        if ($this->output->isDebug()) {
            $this->line($prefix.'Debug mode enabled - showing all operations');
        }

        $this->displayNewRecords($comparison, $prefix, $isDryRun);

        $added = ['regions' => 0, 'provinces' => 0, 'municipalities' => 0];
        if (! $isDryRun) {
            $result = $updateService->applyNew($comparison);
            $added = $result['added'];
        } else {
            $added = [
                'regions' => $comparison->regions->countNew(),
                'provinces' => $comparison->provinces->countNew(),
                'municipalities' => $comparison->municipalities->countNew(),
            ];
        }

        $totalAdded = $added['regions'] + $added['provinces'] + $added['municipalities'];
        $this->info($prefix."Update completed: {$totalAdded} added, 0 modified, 0 deleted");

        return self::SUCCESS;
    }

    private function displayNewRecords(ComparisonResult $comparison, string $prefix, bool $isDryRun): void
    {
        if (! $this->output->isVerbose()) {
            return;
        }

        foreach ($comparison->regions->new as $istatCode => $data) {
            $this->line($prefix."New region: {$data['name']} (ISTAT: {$istatCode})");
        }

        foreach ($comparison->provinces->new as $istatCode => $data) {
            $this->line($prefix."New province: {$data['name']} (ISTAT: {$istatCode})");
        }

        foreach ($comparison->municipalities->new as $istatCode => $data) {
            $this->line($prefix."New municipality: {$data['name']} (ISTAT: {$istatCode})");
        }
    }
}
