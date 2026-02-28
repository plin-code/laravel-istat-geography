<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Services;

use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;

final class GeographyUpdateService
{
    private ?string $connection = null;

    /** @var class-string<Region> */
    private string $regionModel;

    /** @var class-string<Province> */
    private string $provinceModel;

    /** @var class-string<Municipality> */
    private string $municipalityModel;

    public function __construct()
    {
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

    public function applyNew(ComparisonResult $comparison, ?string $connection = null): array
    {
        $this->connection = $connection ?? config('database.default');

        $regionIdMap = $this->buildExistingRegionIdMap();
        $provinceIdMap = $this->buildExistingProvinceIdMap();

        $addedRegions = $this->createNewRegions($comparison->regions->new, $regionIdMap);

        $addedProvinces = $this->createNewProvinces($comparison->provinces->new, $regionIdMap, $provinceIdMap);

        $addedMunicipalities = $this->createNewMunicipalities($comparison->municipalities->new, $provinceIdMap);

        return [
            'added' => [
                'regions' => $addedRegions,
                'provinces' => $addedProvinces,
                'municipalities' => $addedMunicipalities,
            ],
        ];
    }

    public function applyModifications(ComparisonResult $comparison, ?string $connection = null): array
    {
        $this->connection = $connection ?? config('database.default');

        $modifiedRegions = $this->updateModifiedRecords(
            $comparison->regions->modified,
            $this->regionModel
        );

        $modifiedProvinces = $this->updateModifiedRecords(
            $comparison->provinces->modified,
            $this->provinceModel
        );

        $modifiedMunicipalities = $this->updateModifiedRecords(
            $comparison->municipalities->modified,
            $this->municipalityModel
        );

        return [
            'modified' => [
                'regions' => $modifiedRegions,
                'provinces' => $modifiedProvinces,
                'municipalities' => $modifiedMunicipalities,
            ],
        ];
    }

    private function updateModifiedRecords(array $modifiedRecords, string $modelClass): int
    {
        $count = 0;

        foreach ($modifiedRecords as $data) {
            $id = $data['id'];
            $changes = $data['changes'];

            $updateData = [];
            foreach ($changes as $field => $change) {
                $updateData[$field] = $change['new'];
            }

            if (empty($updateData)) {
                continue;
            }

            /** @var Region|Province|Municipality $model */
            $model = new $modelClass;
            $record = $model
                ->setConnection($this->connection)
                ->withoutTrashed()
                ->find($id);

            if ($record !== null) {
                $record->update($updateData);
                $count++;
            }
        }

        return $count;
    }

    private function buildExistingRegionIdMap(): array
    {
        /** @var Region $model */
        $model = new ($this->regionModel);

        return $model
            ->setConnection($this->connection)
            ->withoutTrashed()
            ->pluck('id', 'istat_code')
            ->all();
    }

    private function buildExistingProvinceIdMap(): array
    {
        /** @var Province $model */
        $model = new ($this->provinceModel);

        return $model
            ->setConnection($this->connection)
            ->withoutTrashed()
            ->pluck('id', 'istat_code')
            ->all();
    }

    private function createNewRegions(array $newRegions, array &$regionIdMap): int
    {
        $count = 0;

        foreach ($newRegions as $istatCode => $data) {
            /** @var Region $model */
            $model = new ($this->regionModel);
            $region = $model
                ->setConnection($this->connection)
                ->create([
                    'istat_code' => $data['istat_code'],
                    'name' => $data['name'],
                ]);

            $regionIdMap[$istatCode] = $region->id;
            $count++;
        }

        return $count;
    }

    private function createNewProvinces(array $newProvinces, array $regionIdMap, array &$provinceIdMap): int
    {
        $count = 0;

        foreach ($newProvinces as $istatCode => $data) {
            $regionId = $data['region_id'] ?? null;
            if ($regionId === null && isset($data['region_istat_code'])) {
                $regionId = $regionIdMap[$data['region_istat_code']] ?? null;
            }

            /** @var Province $model */
            $model = new ($this->provinceModel);
            $province = $model
                ->setConnection($this->connection)
                ->create([
                    'istat_code' => $data['istat_code'],
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'region_id' => $regionId,
                ]);

            $provinceIdMap[$istatCode] = $province->id;
            $count++;
        }

        return $count;
    }

    private function createNewMunicipalities(array $newMunicipalities, array $provinceIdMap): int
    {
        $count = 0;

        foreach ($newMunicipalities as $data) {
            $provinceId = $data['province_id'] ?? null;
            if ($provinceId === null && isset($data['province_istat_code'])) {
                $provinceId = $provinceIdMap[$data['province_istat_code']] ?? null;
            }

            /** @var Municipality $model */
            $model = new ($this->municipalityModel);
            $model
                ->setConnection($this->connection)
                ->create([
                    'istat_code' => $data['istat_code'],
                    'name' => $data['name'],
                    'province_id' => $provinceId,
                ]);

            $count++;
        }

        return $count;
    }
}
