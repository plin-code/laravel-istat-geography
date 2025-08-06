<?php

namespace PlinCode\IstatGeographical;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use PlinCode\IstatGeographical\Commands\IstatGeographicalCommand;

class IstatGeographicalServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-istat-geographical-dataset')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_istat_geographical_dataset_table')
            ->hasCommand(IstatGeographicalCommand::class);
    }
}
