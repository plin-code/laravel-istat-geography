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

        // Display modified records with details at -vv verbosity
        $this->displayModifiedRecords($comparison, $prefix);

        // Display suppressed records
        $this->displaySuppressedRecords($comparison, $prefix);

        $added = ['regions' => 0, 'provinces' => 0, 'municipalities' => 0];
        $modified = ['regions' => 0, 'provinces' => 0, 'municipalities' => 0];
        $suppressed = ['regions' => 0, 'provinces' => 0, 'municipalities' => 0];

        if (! $isDryRun) {
            $addResult = $updateService->applyNew($comparison);
            $added = $addResult['added'];

            $modifyResult = $updateService->applyModifications($comparison);
            $modified = $modifyResult['modified'];

            $suppressResult = $updateService->applySuppressed($comparison);
            $suppressed = $suppressResult['suppressed'];
        } else {
            $added = [
                'regions' => $comparison->regions->countNew(),
                'provinces' => $comparison->provinces->countNew(),
                'municipalities' => $comparison->municipalities->countNew(),
            ];
            $modified = [
                'regions' => $comparison->regions->countModified(),
                'provinces' => $comparison->provinces->countModified(),
                'municipalities' => $comparison->municipalities->countModified(),
            ];
            $suppressed = [
                'regions' => $comparison->regions->countSuppressed(),
                'provinces' => $comparison->provinces->countSuppressed(),
                'municipalities' => $comparison->municipalities->countSuppressed(),
            ];
        }

        $totalAdded = $added['regions'] + $added['provinces'] + $added['municipalities'];
        $totalModified = $modified['regions'] + $modified['provinces'] + $modified['municipalities'];
        $totalSuppressed = $suppressed['regions'] + $suppressed['provinces'] + $suppressed['municipalities'];
        $this->info($prefix."Update completed: {$totalAdded} added, {$totalModified} modified, {$totalSuppressed} deleted");

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

    private function displayModifiedRecords(ComparisonResult $comparison, string $prefix): void
    {
        if (! $this->output->isVerbose()) {
            return;
        }

        foreach ($comparison->regions->modified as $istatCode => $data) {
            $this->line($prefix."Modified region (ISTAT: {$istatCode})");
            $this->displayFieldChanges($data['changes'], $prefix);
        }

        foreach ($comparison->provinces->modified as $istatCode => $data) {
            $this->line($prefix."Modified province (ISTAT: {$istatCode})");
            $this->displayFieldChanges($data['changes'], $prefix);
        }

        foreach ($comparison->municipalities->modified as $istatCode => $data) {
            $this->line($prefix."Modified municipality (ISTAT: {$istatCode})");
            $this->displayFieldChanges($data['changes'], $prefix);
        }
    }

    private function displayFieldChanges(array $changes, string $prefix): void
    {
        if (! $this->output->isVeryVerbose()) {
            return;
        }

        foreach ($changes as $field => $change) {
            $old = $change['old'] ?? 'null';
            $new = $change['new'] ?? 'null';
            $this->line($prefix."  {$field}: {$old} → {$new}");
        }
    }

    private function displaySuppressedRecords(ComparisonResult $comparison, string $prefix): void
    {
        if (! $this->output->isVerbose()) {
            return;
        }

        foreach ($comparison->regions->suppressed as $istatCode => $data) {
            $this->line($prefix."Suppressed region: {$data['name']} (ISTAT: {$istatCode})");
        }

        foreach ($comparison->provinces->suppressed as $istatCode => $data) {
            $this->line($prefix."Suppressed province: {$data['name']} (ISTAT: {$istatCode})");
        }

        foreach ($comparison->municipalities->suppressed as $istatCode => $data) {
            $this->line($prefix."Suppressed municipality: {$data['name']} (ISTAT: {$istatCode})");
        }
    }
}
