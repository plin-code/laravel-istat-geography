<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PlinCode\IstatGeography\Services\ComparisonResult;
use PlinCode\IstatGeography\Services\GeographyCompareService;
use PlinCode\IstatGeography\Services\GeographyUpdateService;

class IstatGeographyUpdateCommand extends Command
{
    protected $signature = 'geography:update
                            {--dry-run : Simulate the update without making changes}
                            {--force : Continue despite non-critical errors}';

    protected $description = 'Update geographical data by synchronizing with the latest ISTAT data (adds new records, updates existing ones, soft-deletes removed ones)';

    /** @var array<string> */
    private array $warnings = [];

    public function handle(
        GeographyCompareService $compareService,
        GeographyUpdateService $updateService
    ): int {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');
        $prefix = $isDryRun ? '[DRY-RUN] ' : '';

        $this->info($prefix.'Starting geographical data update...');

        try {
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

            $this->displayNewRecords($comparison, $prefix);

            $this->displayModifiedRecords($comparison, $prefix);

            $this->displaySuppressedRecords($comparison, $prefix);

            if (! $isDryRun) {
                $result = DB::transaction(function () use ($updateService, $comparison, $isForce) {
                    return $this->applyChanges($updateService, $comparison, $isForce);
                });
                $added = $result['added'];
                $modified = $result['modified'];
                $suppressed = $result['suppressed'];
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

            $this->displayWarnings();

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Update failed: '.$e->getMessage());
            $this->error('All changes have been rolled back.');

            return self::FAILURE;
        }
    }

    private function applyChanges(
        GeographyUpdateService $updateService,
        ComparisonResult $comparison,
        bool $isForce
    ): array {
        $added = ['regions' => 0, 'provinces' => 0, 'municipalities' => 0];
        $modified = ['regions' => 0, 'provinces' => 0, 'municipalities' => 0];
        $suppressed = ['regions' => 0, 'provinces' => 0, 'municipalities' => 0];

        try {
            $addResult = $updateService->applyNew($comparison);
            $added = $addResult['added'];
        } catch (Exception $e) {
            $this->handleOperationError('adding new records', $e, $isForce);
        }

        try {
            $modifyResult = $updateService->applyModifications($comparison);
            $modified = $modifyResult['modified'];
        } catch (Exception $e) {
            $this->handleOperationError('updating records', $e, $isForce);
        }

        try {
            $suppressResult = $updateService->applySuppressed($comparison);
            $suppressed = $suppressResult['suppressed'];
        } catch (Exception $e) {
            $this->handleOperationError('deleting records', $e, $isForce);
        }

        return [
            'added' => $added,
            'modified' => $modified,
            'suppressed' => $suppressed,
        ];
    }

    private function handleOperationError(string $operation, Exception $e, bool $isForce): void
    {
        $message = "Error while {$operation}: {$e->getMessage()}";

        if ($isForce) {
            $this->warnings[] = $message;
            Log::warning("[geography:update] {$message}");
        } else {
            throw $e;
        }
    }

    private function displayWarnings(): void
    {
        if (empty($this->warnings)) {
            return;
        }

        $this->warn('Warnings:');
        foreach ($this->warnings as $warning) {
            $this->warn('  - '.$warning);
        }
    }

    private function displayNewRecords(ComparisonResult $comparison, string $prefix): void
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
