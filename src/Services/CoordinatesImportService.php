<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use RuntimeException;

final class CoordinatesImportService
{
    private const CHUNK_SIZE = 500;

    private ?string $connection = null;

    private string $datasetUrl;

    private string $tempFilename;

    private string $municipalityModel;

    private ?string $localFilePath = null;

    public function __construct()
    {
        $this->datasetUrl = config('istat-geography.coordinates.dataset_url');
        $this->tempFilename = config('istat-geography.coordinates.temp_filename');
        $this->municipalityModel = config('istat-geography.models.municipality');
    }

    /**
     * Set a local file path (plain or gzipped JSON) to use instead of downloading.
     */
    public function useLocalFile(string $path): self
    {
        $this->localFilePath = $path;

        return $this;
    }

    public function execute(?string $connection = null): int
    {
        $this->connection = $connection;

        try {
            $filePath = $this->localFilePath ?? $this->downloadDataset();
            $entries = $this->parseDataset($filePath);

            $updatedCount = 0;
            $notFoundCount = 0;
            $invalidCount = 0;

            foreach (array_chunk($entries, self::CHUNK_SIZE) as $chunk) {
                $municipalities = $this->findMunicipalities(array_column($chunk, 'istat_code'));

                foreach ($chunk as $entry) {
                    $istatCode = is_array($entry) ? ($entry['istat_code'] ?? null) : null;

                    if (! is_array($entry) || ! is_string($istatCode) || ! $this->hasValidCoordinates($entry)) {
                        Log::warning('Coordinates import: skipped an entry with a missing istat_code or invalid coordinates', [
                            'istat_code' => $istatCode,
                        ]);
                        $invalidCount++;

                        continue;
                    }

                    $municipality = $municipalities[$istatCode] ?? null;

                    if (! $municipality) {
                        Log::warning("Coordinates import: no municipality found for istat_code '{$istatCode}'");
                        $notFoundCount++;

                        continue;
                    }

                    $municipality->update([
                        'latitude' => round((float) $entry['latitude'], 7),
                        'longitude' => round((float) $entry['longitude'], 7),
                    ]);

                    $updatedCount++;
                }
            }

            if ($notFoundCount > 0 || $invalidCount > 0) {
                Log::info("Coordinates import completed: {$updatedCount} updated, {$notFoundCount} not found, {$invalidCount} invalid");
            }

            return $updatedCount;
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to import coordinates data: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * @param  array<int, mixed>  $istatCodes
     * @return array<string, Municipality>
     */
    private function findMunicipalities(array $istatCodes): array
    {
        /** @var Municipality $model */
        $model = new ($this->municipalityModel);

        return $model
            ->setConnection($this->connection ?? config('istat-geography.connection') ?? config('database.default'))
            ->whereIn('istat_code', array_filter($istatCodes, 'is_string'))
            ->get()
            ->keyBy('istat_code')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function hasValidCoordinates(array $entry): bool
    {
        $latitude = $entry['latitude'] ?? null;
        $longitude = $entry['longitude'] ?? null;

        return is_numeric($latitude)
            && is_numeric($longitude)
            && abs((float) $latitude) <= 90
            && abs((float) $longitude) <= 180;
    }

    private function downloadDataset(): string
    {
        $storage = Storage::disk('local');
        $filePath = $storage->path($this->tempFilename);

        if ($storage->exists($this->tempFilename)) {
            $lastModified = filemtime($filePath);
            if ($lastModified && date('Y-m-d') === date('Y-m-d', $lastModified)) {
                return $filePath;
            }
        }

        $response = Http::timeout(300)->get($this->datasetUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to download coordinates data: HTTP '.$response->status());
        }

        $storage->put($this->tempFilename, $this->decompress($response->body()));

        return $filePath;
    }

    /**
     * Parse the coordinates dataset: an object with a "municipalities" array.
     *
     * @return list<mixed>
     */
    private function parseDataset(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Failed to read coordinates file: {$path}");
        }

        $data = json_decode($this->decompress($content), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON: '.json_last_error_msg());
        }

        if (! is_array($data) || ! isset($data['municipalities']) || ! is_array($data['municipalities'])) {
            throw new RuntimeException('Invalid coordinates data format: expected an object with a "municipalities" array');
        }

        return array_values($data['municipalities']);
    }

    private function decompress(string $content): string
    {
        $isGzip = strlen($content) >= 2 && ord($content[0]) === 0x1F && ord($content[1]) === 0x8B;

        if (! $isGzip) {
            return $content;
        }

        $decompressed = gzdecode($content);

        if ($decompressed === false) {
            throw new RuntimeException('Failed to decompress gzip data');
        }

        return $decompressed;
    }
}
