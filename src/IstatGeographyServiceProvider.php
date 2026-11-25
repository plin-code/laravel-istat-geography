<?php

declare(strict_types=1);

namespace PlinCode\IstatGeography;

use Illuminate\Support\Facades\Facade;
use PlinCode\IstatGeography\Commands\DownloadCapCommand;
use PlinCode\IstatGeography\Commands\DownloadCoordinatesCommand;
use PlinCode\IstatGeography\Commands\IstatGeographyCommand;
use PlinCode\IstatGeography\Commands\IstatGeographyUpdateCommand;
use PlinCode\IstatGeography\Services\CapImportService;
use PlinCode\IstatGeography\Services\CoordinatesImportService;
use PlinCode\IstatGeography\Services\GeographyImportService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IstatGeographyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-istat-geography')
            ->hasConfigFile('istat-geography')
            ->hasViews()
            ->hasMigration('create_istat_geography_table')
            ->hasMigration('extend_municipalities_with_postal_codes')
            ->hasMigration('extend_municipalities_with_coordinates')
            ->hasCommand(IstatGeographyCommand::class)
            ->hasCommand(IstatGeographyUpdateCommand::class)
            ->hasCommand(DownloadCapCommand::class)
            ->hasCommand(DownloadCoordinatesCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('istat-geography', function ($app) {
            return new IstatGeography(
                $app->make(GeographyImportService::class)
            );
        });

        $this->app->singleton(GeographyImportService::class);
        $this->app->singleton(CapImportService::class);
        $this->app->singleton(CoordinatesImportService::class);
    }

    public function packageBooted(): void
    {
        Facade::clearResolvedInstance('istat-geography');
    }
}
