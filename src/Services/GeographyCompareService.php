<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use RuntimeException;

final class GeographyCompareService
{
    private ?string $connection = null;

    private string $csvUrl;

    private string $tempFilename;

    /** @var class-string<Region> */
    private string $regionModel;

    /** @var class-string<Province> */
    private string $provinceModel;

    /** @var class-string<Municipality> */
    private string $municipalityModel;

    public function __construct()
    {
        /** @var string $csvUrl */
        $csvUrl = config('istat-geography.import.csv_url');
        $this->csvUrl = $csvUrl;

        /** @var string $tempFilename */
        $tempFilename = config('istat-geography.import.temp_filename');
        $this->tempFilename = $tempFilename;

        /** @var class-string<Region> $regionModel */
        $regionModel = config('istat-geography.models.region');
        $this->regionModel = $regionModel;

        /** @var class-string<Province> $provinceModel */
        $provinceModel = config('istat-geography.models.province');
        $this->provinceModel = $provinceModel;

        /** @var class-string<Municipality> $municipalityModel */
        $municipalityModel = config('istat-geography.models.municipality');
        $this->municipalityModel = $municipalityModel;
    }

    /**
     * Compare ISTAT data with existing database records.
     *
     * @return ComparisonResult The comparison results containing new, modified, and suppressed records
     */
    public function compare(?string $connection = null): ComparisonResult
    {
        $this->connection = $connection;

        try {
            $csvPath = $this->downloadCsv();
            $istatData = $this->parseIstatData($csvPath);
            $dbData = $this->loadDatabaseData();

            return $this->compareData($istatData, $dbData);
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to compare geographic data: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * @return array{regions: array<string, array<string, mixed>>, provinces: array<string, array<string, mixed>>, municipalities: array<string, array<string, mixed>>}
     */
    private function parseIstatData(string $csvPath): array
    {
        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setOutputBOM(\League\Csv\Bom::Utf8);
        $csv->appendStreamFilterOnRead('convert.iconv.ISO-8859-15/UTF-8');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $regions = [];
        $provinces = [];
        $municipalities = [];

        foreach ($csv->getRecords() as $record) {
            $values = array_values($record);

            // Region data (indexed by istat_code)
            $regionIstatCode = $values[0];
            if (! isset($regions[$regionIstatCode])) {
                $regions[$regionIstatCode] = [
                    'istat_code' => $regionIstatCode,
                    'name' => $values[10],
                ];
            }

            // Province data (indexed by istat_code)
            $provinceIstatCode = $values[2];
            if (! isset($provinces[$provinceIstatCode])) {
                $provinces[$provinceIstatCode] = [
                    'istat_code' => $provinceIstatCode,
                    'name' => $values[11],
                    'code' => $values[14],
                    'region_istat_code' => $regionIstatCode,
                ];
            }

            // Municipality data (indexed by istat_code)
            $municipalityIstatCode = $values[4];
            $municipalities[$municipalityIstatCode] = [
                'istat_code' => $municipalityIstatCode,
                'name' => $values[5],
                'province_istat_code' => $provinceIstatCode,
            ];
        }

        return [
            'regions' => $regions,
            'provinces' => $provinces,
            'municipalities' => $municipalities,
        ];
    }

    /**
     * @return array{regions: array<string, array<string, mixed>>, provinces: array<string, array<string, mixed>>, municipalities: array<string, array<string, mixed>>}
     */
    private function loadDatabaseData(): array
    {
        $connection = $this->connection ?? config('database.default');

        /** @var Region $regionModel */
        $regionModel = new ($this->regionModel);
        $regions = $regionModel
            ->setConnection($connection)
            ->withoutTrashed()
            ->get()
            ->keyBy('istat_code')
            ->map(fn ($model) => $model->toArray())
            ->all();

        /** @var Province $provinceModel */
        $provinceModel = new ($this->provinceModel);
        $provinces = $provinceModel
            ->setConnection($connection)
            ->withoutTrashed()
            ->get()
            ->keyBy('istat_code')
            ->map(fn ($model) => $model->toArray())
            ->all();

        /** @var Municipality $municipalityModel */
        $municipalityModel = new ($this->municipalityModel);
        $municipalities = $municipalityModel
            ->setConnection($connection)
            ->withoutTrashed()
            ->get()
            ->keyBy('istat_code')
            ->map(fn ($model) => $model->toArray())
            ->all();

        return [
            'regions' => $regions,
            'provinces' => $provinces,
            'municipalities' => $municipalities,
        ];
    }

    /**
     * @param  array{regions: array<string, array<string, mixed>>, provinces: array<string, array<string, mixed>>, municipalities: array<string, array<string, mixed>>}  $istatData
     * @param  array{regions: array<string, array<string, mixed>>, provinces: array<string, array<string, mixed>>, municipalities: array<string, array<string, mixed>>}  $dbData
     */
    private function compareData(array $istatData, array $dbData): ComparisonResult
    {
        // Load region ID mapping for resolving foreign keys
        $regionIdMap = $this->buildRegionIdMap($dbData['regions']);
        $provinceIdMap = $this->buildProvinceIdMap($dbData['provinces']);

        return new ComparisonResult(
            regions: $this->compareEntity(
                $istatData['regions'],
                $dbData['regions'],
                $this->regionModel::istatFields()
            ),
            provinces: $this->compareEntity(
                $istatData['provinces'],
                $dbData['provinces'],
                $this->provinceModel::istatFields(),
                $regionIdMap,
                'region_istat_code',
                'region_id'
            ),
            municipalities: $this->compareEntity(
                $istatData['municipalities'],
                $dbData['municipalities'],
                $this->municipalityModel::istatFields(),
                $provinceIdMap,
                'province_istat_code',
                'province_id'
            )
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $regions
     * @return array<string, string>
     */
    private function buildRegionIdMap(array $regions): array
    {
        $map = [];
        foreach ($regions as $istatCode => $region) {
            if (isset($region['id'])) {
                $map[$istatCode] = $region['id'];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $provinces
     * @return array<string, string>
     */
    private function buildProvinceIdMap(array $provinces): array
    {
        $map = [];
        foreach ($provinces as $istatCode => $province) {
            if (isset($province['id'])) {
                $map[$istatCode] = $province['id'];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $istatRecords
     * @param  array<string, array<string, mixed>>  $dbRecords
     * @param  list<string>  $istatFields
     * @param  array<string, string>|null  $parentIdMap
     */
    private function compareEntity(
        array $istatRecords,
        array $dbRecords,
        array $istatFields,
        ?array $parentIdMap = null,
        ?string $parentIstatCodeField = null,
        ?string $parentIdField = null
    ): EntityComparisonResult {
        $new = [];
        $modified = [];
        $suppressed = [];

        $istatCodes = array_keys($istatRecords);
        $dbCodes = array_keys($dbRecords);

        // Find new records (in ISTAT, not in DB)
        $newCodes = array_diff($istatCodes, $dbCodes);
        foreach ($newCodes as $code) {
            $record = $istatRecords[$code];
            if ($parentIdMap !== null && $parentIstatCodeField !== null && $parentIdField !== null) {
                $parentIstatCode = $record[$parentIstatCodeField] ?? null;
                if ($parentIstatCode !== null && isset($parentIdMap[$parentIstatCode])) {
                    $record[$parentIdField] = $parentIdMap[$parentIstatCode];
                    unset($record[$parentIstatCodeField]);
                }
            }
            $new[$code] = $record;
        }

        $existingCodes = array_intersect($istatCodes, $dbCodes);
        foreach ($existingCodes as $code) {
            $istatRecord = $istatRecords[$code];
            $dbRecord = $dbRecords[$code];

            if ($parentIdMap !== null && $parentIstatCodeField !== null && $parentIdField !== null) {
                $parentIstatCode = $istatRecord[$parentIstatCodeField] ?? null;
                if ($parentIstatCode !== null && isset($parentIdMap[$parentIstatCode])) {
                    $istatRecord[$parentIdField] = $parentIdMap[$parentIstatCode];
                }
                unset($istatRecord[$parentIstatCodeField]);
            }

            $changes = $this->findFieldChanges($istatRecord, $dbRecord, $istatFields);
            if (! empty($changes)) {
                $modified[$code] = [
                    'id' => $dbRecord['id'],
                    'changes' => $changes,
                ];
            }
        }

        $suppressedCodes = array_diff($dbCodes, $istatCodes);
        foreach ($suppressedCodes as $code) {
            $suppressed[$code] = [
                'id' => $dbRecords[$code]['id'],
                'name' => $dbRecords[$code]['name'] ?? null,
            ];
        }

        return new EntityComparisonResult(
            new: $new,
            modified: $modified,
            suppressed: $suppressed
        );
    }

    /**
     * @param  array<string, mixed>  $istatRecord
     * @param  array<string, mixed>  $dbRecord
     * @param  list<string>  $istatFields
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function findFieldChanges(array $istatRecord, array $dbRecord, array $istatFields): array
    {
        $changes = [];

        foreach ($istatFields as $field) {
            if (! array_key_exists($field, $istatRecord)) {
                continue;
            }

            $istatValue = $istatRecord[$field];
            $dbValue = $dbRecord[$field] ?? null;

            // Compare as strings to handle type differences
            if ((string) $istatValue !== (string) $dbValue) {
                $changes[$field] = [
                    'old' => $dbValue,
                    'new' => $istatValue,
                ];
            }
        }

        return $changes;
    }

    private function downloadCsv(): string
    {
        $storage = Storage::disk('local');
        $filePath = $storage->path($this->tempFilename);

        if ($storage->exists($this->tempFilename)) {
            $lastModified = filemtime($filePath);
            if ($lastModified && date('Y-m-d') === date('Y-m-d', $lastModified)) {
                return $filePath;
            }
        }

        $response = Http::get($this->csvUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to download CSV from ISTAT');
        }

        $storage->put($this->tempFilename, $response->body());

        return $filePath;
    }
}
