<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DownloadCoordinatesCommand extends Command
{
    protected $signature = 'geography:download-coordinates
                            {--url= : Custom URL to download from (overrides config)}
                            {--output= : Output file path (default: storage/app/coordinates-dataset.json)}';

    protected $description = 'Download the municipality coordinates dataset and save it locally for offline import';

    public function handle(): int
    {
        $url = $this->option('url') ?: config('istat-geography.coordinates.dataset_url');
        $output = $this->option('output') ?: storage_path('app/coordinates-dataset.json');

        $this->info("Downloading coordinates dataset from: {$url}");
        $this->info("Output file: {$output}");

        try {
            $response = Http::timeout(300)->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("Download failed with status: {$response->status()}");
            }

            $content = $response->body();

            $isGzip = strlen($content) >= 2 && ord($content[0]) === 0x1F && ord($content[1]) === 0x8B;

            if ($isGzip) {
                $this->info('Decompressing...');
                $decompressed = gzdecode($content);
                if ($decompressed === false) {
                    throw new RuntimeException('Failed to decompress gzip data');
                }
                $content = $decompressed;
            }

            if (file_put_contents($output, $content) === false) {
                throw new RuntimeException("Unable to write the output file: {$output}");
            }

            $sizeKb = round(strlen($content) / 1024, 2);

            $this->info("Download completed! File size: {$sizeKb} KB");
            $this->newLine();
            $this->line('To import the coordinates, run:');
            $this->line("  php artisan geography:import --coordinates-only --coordinates-file={$output}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Download failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
