<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DownloadCapCommand extends Command
{
    protected $signature = 'geography:download-cap
                            {--url= : Custom URL to download from (overrides config)}
                            {--output= : Output file path (default: storage/app/cap-dataset.json)}';

    protected $description = 'Download CAP GeoJSON data and save it locally for offline import';

    public function handle(): int
    {
        $url = $this->option('url') ?: config('istat-geography.cap.geojson_url');
        $output = $this->option('output') ?: storage_path('app/cap-boundaries.geojson');

        $this->info("Downloading CAP GeoJSON from: {$url}");
        $this->info("Output file: {$output}");

        try {
            $this->info('Downloading...');

            $response = Http::timeout(600)->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("Download failed with status: {$response->status()}");
            }

            $content = $response->body();

            // Check if content is gzipped
            $isGzip = strlen($content) >= 2 && ord($content[0]) === 0x1F && ord($content[1]) === 0x8B;

            if ($isGzip) {
                $this->info('Decompressing...');
                $decompressed = gzdecode($content);
                if ($decompressed === false) {
                    throw new RuntimeException('Failed to decompress gzip data');
                }
                $content = $decompressed;
            }

            file_put_contents($output, $content);

            $size = filesize($output);
            $sizeMb = round($size / 1024 / 1024, 2);

            $this->info("Download completed! File size: {$sizeMb} MB");
            $this->newLine();
            $this->line('To import CAP data, run:');
            $this->line("  php artisan geography:import --cap --cap-file={$output}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Download failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
