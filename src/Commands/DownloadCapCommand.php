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
        $output = $this->option('output') ?: storage_path('app/cap-dataset.json');

        $this->info("Downloading CAP data from: {$url}");
        $this->info("Output file: {$output}");

        try {
            $response = Http::timeout(600)
                ->withOptions(['sink' => $output])
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("Download failed with status: {$response->status()}");
            }

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
