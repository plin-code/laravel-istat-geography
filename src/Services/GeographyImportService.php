<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\UnavailableFeature;
use League\Csv\UnavailableStream;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use RuntimeException;

final class GeographyImportService
{
    private ?string $connection = null;

    private string $csvUrl;

    private string $tempFilename;

    private string $regionModel;

    private string $provinceModel;

    private string $municipalityModel;

    public function __construct()
    {
        $this->csvUrl = config('istat-geography.import.csv_url');
        $this->tempFilename = config('istat-geography.import.temp_filename');
        $this->regionModel = config('istat-geography.models.region');
        $this->provinceModel = config('istat-geography.models.province');
        $this->municipalityModel = config('istat-geography.models.municipality');
    }

    public function execute(?string $connection = null): int
    {
        $this->connection = $connection;
        try {
            $csvPath = $this->downloadCsv();
            $records = $this->prepareCsvReader($csvPath)->getRecords();

            $regions = [];
            $provinces = [];
            $count = 0;

            foreach ($records as $record) {
                $values = array_values($record);

                $regionName = $values[10];
                $regions[$regionName] ??= $this->processRegion(
                    name: $regionName,
                    istatCode: $values[0]
                );

                $provinceName = $values[11];
                $provinceKey = "{$provinceName}-{$values[14]}";
                $provinces[$provinceKey] ??= $this->processProvince(
                    name: $provinceName,
                    istatCode: $values[2],
                    abbreviation: $values[14],
                    regionId: $regions[$regionName]
                );

                $this->processMunicipality(
                    name: $values[5],
                    istatCode: $values[4],
                    provinceId: $provinces[$provinceKey]
                );

                $count++;
            }

            return $count;

        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to import geographic data: {$e->getMessage()}", previous: $e);
        }
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

    /**
     * @throws InvalidArgument
     * @throws UnavailableStream
     * @throws UnavailableFeature
     * @throws Exception
     */
    private function prepareCsvReader(string $path): Reader
    {
        $csv = Reader::createFromPath($path, 'r');

        // Configura il reader per gestire l'encoding del file ISTAT
        $csv->appendStreamFilterOnRead('convert.iconv.ISO-8859-15/UTF-8');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        return $csv;
    }

    private function processRegion(string $name, string $istatCode): string
    {
        /** @var Region $model */
        $model = new ($this->regionModel);

        return $model
            ->setConnection($this->connection ?? config('database.default'))
            ->updateOrCreate(
                ['istat_code' => $istatCode],
                ['name' => $name]
            )->id;
    }

    private function processProvince(string $name, string $istatCode, string $abbreviation, string $regionId): string
    {
        /** @var Province $model */
        $model = new ($this->provinceModel);

        return $model
            ->setConnection($this->connection ?? config('database.default'))
            ->updateOrCreate(
                ['istat_code' => $istatCode],
                [
                    'name' => $name,
                    'code' => $abbreviation,
                    'region_id' => $regionId,
                ]
            )->id;
    }

    private function processMunicipality(string $name, string $istatCode, string $provinceId): void
    {
        /** @var Municipality $model */
        $model = new ($this->municipalityModel);
        $model
            ->setConnection($this->connection ?? config('database.default'))
            ->updateOrCreate(
                ['istat_code' => $istatCode],
                [
                    'name' => $name,
                    'province_id' => $provinceId,
                ]
            );
    }
}
