<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Bom;
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
        $csv->setOutputBOM(Bom::Utf8);
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

            // A missing or malformed Belfiore (cadastral) code is left out so it never overwrites a stored value
            $belCode = trim($values[19] ?? '');
            if (preg_match('/^[A-Z]\d{3}$/', $belCode) === 1) {
                $municipalities[$municipalityIstatCode]['bel_code'] = $belCode;
            } elseif ($belCode !== '') {
                // A malformed value may mean ISTAT moved the columns, so leave a trace
                Log::warning("geography:update: unexpected Belfiore code '{$belCode}' for municipality {$municipalityIstatCode}, skipped");
            }
        }

        return [
            'regions' => $regions,
            'provinces' => $provinces,
            'municipalities' => $municipalities,
        ];
    }

    /**
     * @return array{regions: array<string, array<string, mixed>>, provinces: array<string, array<string, mixed>>, municipalities: array<string, array<string, mixed>>, trashed_municipality_codes: list<string>}
     */
    private function loadDatabaseData(): array
    {
        $connection = $this->connection ?? config('istat-geography.connection') ?? config('database.default');

        /** @var Region $regionModel */
        $regionModel = new ($this->regionModel);
        $regions = $regionModel
            ->setConnection($connection)
            ->withoutTrashed()
            ->get()
            ->keyBy('istat_code')
            ->map(fn ($model) => $model->getAttributes())
            ->all();

        /** @var Province $provinceModel */
        $provinceModel = new ($this->provinceModel);
        $provinces = $provinceModel
            ->setConnection($connection)
            ->withoutTrashed()
            ->get()
            ->keyBy('istat_code')
            ->map(fn ($model) => $model->getAttributes())
            ->all();

        /** @var Municipality $municipalityModel */
        $municipalityModel = new ($this->municipalityModel);
        $municipalities = $municipalityModel
            ->setConnection($connection)
            ->withoutTrashed()
            ->get()
            ->keyBy('istat_code')
            ->map(fn ($model) => $model->getAttributes())
            ->all();

        $trashedMunicipalityCodes = $municipalityModel
            ->setConnection($connection)
            ->onlyTrashed()
            ->pluck('istat_code')
            ->map(fn ($code) => (string) $code)
            ->all();

        return [
            'regions' => $regions,
            'provinces' => $provinces,
            'municipalities' => $municipalities,
            'trashed_municipality_codes' => $trashedMunicipalityCodes,
        ];
    }

    /**
     * @param  array{regions: array<string, array<string, mixed>>, provinces: array<string, array<string, mixed>>, municipalities: array<string, array<string, mixed>>}  $istatData
     * @param  array{regions: array<string, array<string, mixed>>, provinces: array<string, array<string, mixed>>, municipalities: array<string, array<string, mixed>>, trashed_municipality_codes: list<string>}  $dbData
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
            municipalities: $this->compareMunicipalities($istatData, $dbData, $provinceIdMap)
        );
    }

    /**
     * Compare municipalities, keeping the existing record of a municipality whose
     * ISTAT code changed (e.g. because it moved to another province).
     *
     * Records are matched in order by bel_code, by istat_code and, for records
     * stored without a bel_code, by a name that is unique among the unmatched
     * records, then by name and region when the name alone is ambiguous.
     *
     * @param  array{regions: array<string, array<string, mixed>>, provinces: array<string, array<string, mixed>>, municipalities: array<string, array<string, mixed>>}  $istatData
     * @param  array{regions: array<string, array<string, mixed>>, provinces: array<string, array<string, mixed>>, municipalities: array<string, array<string, mixed>>, trashed_municipality_codes: list<string>}  $dbData
     * @param  array<string, string>  $provinceIdMap
     */
    private function compareMunicipalities(array $istatData, array $dbData, array $provinceIdMap): EntityComparisonResult
    {
        $istatRecords = $istatData['municipalities'];
        $dbRecords = $dbData['municipalities'];

        // Phase 1: bel_code, stable across ISTAT code changes
        $matches = $this->matchByUniqueKey(
            $istatRecords,
            $dbRecords,
            fn (array $record): ?string => $this->belCodeOf($record),
            fn (array $record): ?string => $this->belCodeOf($record),
        );

        // Phase 2: istat_code
        $unmatchedIstat = array_diff_key($istatRecords, $matches);
        $unmatchedDb = array_diff_key($dbRecords, array_flip($matches));
        foreach (array_keys(array_intersect_key($unmatchedIstat, $unmatchedDb)) as $code) {
            $matches[(string) $code] = (string) $code;
        }

        // Phase 3: name, only for records stored without a bel_code
        $istatRegionByProvince = array_map(
            fn (array $province): string => (string) $province['region_istat_code'],
            $istatData['provinces']
        );
        $dbRegionByProvinceId = $this->buildRegionCodeByProvinceIdMap($dbData['provinces'], $dbData['regions']);

        // A name alone is trusted only when it is unique in the whole CSV and the whole database
        $istatNameCounts = array_count_values(array_filter(array_map(fn (array $record): ?string => $this->nameOf($record), $istatRecords)));
        $dbNameCounts = array_count_values(array_filter(array_map(fn (array $record): ?string => $this->nameOf($record), $dbRecords)));
        $uniqueNameOf = function (array $record) use ($istatNameCounts, $dbNameCounts): ?string {
            $name = $this->nameOf($record);

            return $name !== null && ($istatNameCounts[$name] ?? 0) === 1 && ($dbNameCounts[$name] ?? 0) === 1 ? $name : null;
        };

        $nameKeys = [
            [$uniqueNameOf, $uniqueNameOf],
            // Phase 3-bis: name and region, for names that are ambiguous on their own
            [
                fn (array $record): ?string => $this->nameAndRegionOf($record, $istatRegionByProvince[$record['province_istat_code'] ?? ''] ?? null),
                fn (array $record): ?string => $this->nameAndRegionOf($record, $dbRegionByProvinceId[$record['province_id'] ?? ''] ?? null),
            ],
        ];

        foreach ($nameKeys as [$istatKey, $dbKey]) {
            $unmatchedIstat = array_diff_key($istatRecords, $matches);
            $unmatchedDbWithoutBelCode = array_filter(
                array_diff_key($dbRecords, array_flip($matches)),
                fn (array $record): bool => $this->belCodeOf($record) === null
            );

            $matches += $this->matchByUniqueKey($unmatchedIstat, $unmatchedDbWithoutBelCode, $istatKey, $dbKey);
        }

        $istatFields = $this->municipalityModel::istatFields();
        $trashedCodes = array_flip($dbData['trashed_municipality_codes']);

        // Stored code => new code, for the records whose ISTAT code changes
        $moves = [];
        foreach ($matches as $istatCode => $dbCode) {
            if ((string) $istatCode !== $dbCode) {
                $moves[$dbCode] = (string) $istatCode;
            }
        }

        // A move can be applied when its new code is free once the update is done:
        // not held by a soft deleted row, nor by a record that keeps it. Moves are
        // ordered so that a code is left before it is reused; swaps stay blocked.
        $orderedMoves = [];
        $blockedMoves = [];
        $resolveMove = function (string $dbCode, array $visiting = []) use (&$resolveMove, &$orderedMoves, &$blockedMoves, $moves, $dbRecords, $trashedCodes): bool {
            if (isset($orderedMoves[$dbCode])) {
                return true;
            }
            if (isset($blockedMoves[$dbCode])) {
                return false;
            }

            $target = $moves[$dbCode];
            $isHeld = isset($dbRecords[$target]);
            $holderMovesAway = $isHeld
                && isset($moves[$target])
                && ! isset($visiting[$target])
                && $resolveMove($target, $visiting + [$dbCode => true]);

            if (isset($trashedCodes[$target]) || ($isHeld && ! $holderMovesAway)) {
                $blockedMoves[$dbCode] = true;

                return false;
            }

            $orderedMoves[$dbCode] = $target;

            return true;
        };

        foreach (array_keys($moves) as $dbCode) {
            $resolveMove((string) $dbCode);
        }

        $orderedMatches = [];
        foreach ($orderedMoves as $dbCode => $istatCode) {
            $orderedMatches[$istatCode] = (string) $dbCode;
        }
        $orderedMatches += $matches;

        $new = [];
        $modified = [];
        $suppressed = [];
        $skipped = [];

        foreach ($orderedMatches as $istatCode => $dbCode) {
            $istatCode = (string) $istatCode;
            $dbRecord = $dbRecords[$dbCode];
            $istatRecord = $istatRecords[$istatCode];

            // Keep the current code but still apply the other changes
            if (isset($blockedMoves[$dbCode])) {
                $skipped[$istatCode] = [
                    'id' => $dbRecord['id'],
                    'name' => $dbRecord['name'] ?? null,
                    'from' => $dbCode,
                    'to' => $istatCode,
                ];
                $istatRecord['istat_code'] = $dbCode;
                $istatCode = $dbCode;
            }

            $provinceIstatCode = $istatRecord['province_istat_code'] ?? null;
            unset($istatRecord['province_istat_code']);

            $provinceIsNew = $provinceIstatCode !== null && ! isset($provinceIdMap[$provinceIstatCode]);
            if (! $provinceIsNew && $provinceIstatCode !== null) {
                $istatRecord['province_id'] = $provinceIdMap[$provinceIstatCode];
            }

            $changes = $this->findFieldChanges($istatRecord, $dbRecord, $istatFields);

            $entry = ['id' => $dbRecord['id'], 'changes' => $changes];
            if ($provinceIsNew) {
                // Resolved once the new province has been created
                $entry['changes']['province_id'] = ['old' => $dbRecord['province_id'] ?? null, 'new' => null];
                $entry['province_istat_code'] = $provinceIstatCode;
            }

            if (! empty($entry['changes'])) {
                $modified[$istatCode] = $entry;
            }
        }

        foreach (array_diff_key($istatRecords, $matches) as $code => $record) {
            // The code still belongs to a stored municipality that moves to another code
            if (isset($dbRecords[$code])) {
                if (! isset($orderedMoves[(string) $code])) {
                    $skipped[(string) $code] = [
                        'id' => null,
                        'name' => $record['name'] ?? null,
                        'from' => null,
                        'to' => (string) $code,
                    ];

                    continue;
                }

                // Created once the stored municipality has left the code
                $record['waits_for_moved_code'] = true;
            }

            $provinceIstatCode = $record['province_istat_code'] ?? null;
            if ($provinceIstatCode !== null && isset($provinceIdMap[$provinceIstatCode])) {
                $record['province_id'] = $provinceIdMap[$provinceIstatCode];
                unset($record['province_istat_code']);
            }
            $new[(string) $code] = $record;
        }

        foreach (array_diff_key($dbRecords, array_flip($matches)) as $code => $record) {
            $suppressed[(string) $code] = [
                'id' => $record['id'],
                'name' => $record['name'] ?? null,
            ];
        }

        return new EntityComparisonResult(
            new: $new,
            modified: $modified,
            suppressed: $suppressed,
            skipped: $skipped,
        );
    }

    /**
     * Pair records whose key is unique on both sides. Records with a null key,
     * or a key shared by several records on either side, stay unmatched.
     *
     * @param  array<string, array<string, mixed>>  $istatRecords
     * @param  array<string, array<string, mixed>>  $dbRecords
     * @param  callable(array<string, mixed>): ?string  $istatKey
     * @param  callable(array<string, mixed>): ?string  $dbKey
     * @return array<string, string> DB ISTAT codes indexed by the matching CSV ISTAT code
     */
    private function matchByUniqueKey(array $istatRecords, array $dbRecords, callable $istatKey, callable $dbKey): array
    {
        $istatByKey = $this->groupCodesByKey($istatRecords, $istatKey);
        $dbByKey = $this->groupCodesByKey($dbRecords, $dbKey);

        $matches = [];
        foreach ($istatByKey as $key => $istatCodes) {
            $dbCodes = $dbByKey[$key] ?? [];
            if (count($istatCodes) === 1 && count($dbCodes) === 1) {
                $matches[$istatCodes[0]] = $dbCodes[0];
            }
        }

        return $matches;
    }

    /**
     * @param  array<string, array<string, mixed>>  $records
     * @param  callable(array<string, mixed>): ?string  $key
     * @return array<string, list<string>>
     */
    private function groupCodesByKey(array $records, callable $key): array
    {
        $groups = [];
        foreach ($records as $code => $record) {
            $value = $key($record);
            if ($value !== null) {
                $groups[$value][] = (string) $code;
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function belCodeOf(array $record): ?string
    {
        $belCode = trim((string) ($record['bel_code'] ?? ''));

        return $belCode === '' ? null : $belCode;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function nameOf(array $record): ?string
    {
        $name = trim((string) ($record['name'] ?? ''));

        return $name === '' ? null : $name;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function nameAndRegionOf(array $record, ?string $regionIstatCode): ?string
    {
        $name = $this->nameOf($record);

        return $name === null || $regionIstatCode === null ? null : $name.'|'.$regionIstatCode;
    }

    /**
     * @param  array<string, array<string, mixed>>  $provinces
     * @param  array<string, array<string, mixed>>  $regions
     * @return array<string, string> Region ISTAT codes indexed by province id
     */
    private function buildRegionCodeByProvinceIdMap(array $provinces, array $regions): array
    {
        $regionCodeById = [];
        foreach ($regions as $istatCode => $region) {
            $regionCodeById[(string) $region['id']] = (string) $istatCode;
        }

        $map = [];
        foreach ($provinces as $province) {
            $regionCode = $regionCodeById[(string) ($province['region_id'] ?? '')] ?? null;
            if ($regionCode !== null) {
                $map[(string) $province['id']] = $regionCode;
            }
        }

        return $map;
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
