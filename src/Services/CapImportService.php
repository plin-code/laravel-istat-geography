<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use RuntimeException;

final class CapImportService
{
    private ?string $connection = null;

    private string $propertiesUrl;

    private string $tempFilename;

    private string $municipalityModel;

    private ?string $localFilePath = null;

    public function __construct()
    {
        $this->propertiesUrl = config('istat-geography.cap.properties_url');
        $this->tempFilename = config('istat-geography.cap.temp_filename');
        $this->municipalityModel = config('istat-geography.models.municipality');
    }

    /**
     * Set a local file path to use instead of downloading.
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
            $filePath = $this->localFilePath ?? $this->downloadCapData();
            $features = $this->parseCapData($filePath);

            $updatedCount = 0;
            $notFoundCount = 0;

            foreach ($features as $properties) {
                $belCode = $properties['codice_bel'] ?? null;

                if (! $belCode) {
                    continue;
                }

                /** @var Municipality $model */
                $model = new ($this->municipalityModel);
                $municipality = $model
                    ->setConnection($this->connection ?? config('database.default'))
                    ->where('bel_code', $belCode)
                    ->first();

                if (! $municipality) {
                    Log::warning("CAP import: no municipality found for bel_code '{$belCode}'");
                    $notFoundCount++;

                    continue;
                }

                $municipality->update([
                    'postal_code' => $properties['cap'] ?? null,
                    'postal_codes' => $properties['comune_cap'] ?? null,
                ]);

                $updatedCount++;
            }

            if ($notFoundCount > 0) {
                Log::info("CAP import completed: {$updatedCount} updated, {$notFoundCount} not found");
            }

            return $updatedCount;

        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to import CAP data: {$e->getMessage()}", previous: $e);
        }
    }

    private function downloadCapData(): string
    {
        $storage = Storage::disk('local');
        $filePath = $storage->path($this->tempFilename);

        if ($storage->exists($this->tempFilename)) {
            $lastModified = filemtime($filePath);
            if ($lastModified && date('Y-m-d') === date('Y-m-d', $lastModified)) {
                return $filePath;
            }
        }

        $response = Http::timeout(300)->get($this->propertiesUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to download CAP data: HTTP '.$response->status());
        }

        $content = $response->body();

        $isGzip = strlen($content) >= 2 && ord($content[0]) === 0x1F && ord($content[1]) === 0x8B;

        if ($isGzip) {
            $decompressed = gzdecode($content);
            if ($decompressed === false) {
                throw new RuntimeException('Failed to decompress gzip data');
            }
            $content = $decompressed;
        }

        $storage->put($this->tempFilename, $content);

        return $filePath;
    }

    /**
     * Parse CAP data from either a GeoJSON FeatureCollection or a simple JSON array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseCapData(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Failed to read CAP file: {$path}");
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON: '.json_last_error_msg());
        }

        if (is_array($data) && ! isset($data['type'])) {
            return $data;
        }

        // Handle GeoJSON FeatureCollection format
        if (isset($data['type']) && $data['type'] === 'FeatureCollection') {
            if (! isset($data['features']) || ! is_array($data['features'])) {
                throw new RuntimeException('Invalid GeoJSON: missing features array');
            }

            return array_map(
                fn (array $feature): array => $feature['properties'] ?? [],
                $data['features']
            );
        }

        throw new RuntimeException('Invalid CAP data format: expected JSON array or GeoJSON FeatureCollection');
    }
}
