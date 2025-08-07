# Laravel ISTAT Geography

A Laravel package for importing and managing Italian geographical data from ISTAT.

## Features

- Automatic import of regions, provinces, and municipalities from ISTAT
- Eloquent models with hierarchical relationships
- Artisan command for importing
- Support for UUID and soft deletes
- Configurable via configuration file

## Installation

```bash
composer require plin-code/laravel-istat-geographical
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="PlinCode\IstatGeography\IstatGeographyServiceProvider"
```

## Migrations

Run the migrations to create the necessary tables:

```bash
php artisan migrate
```

## Usage

### Data Import

To import all geographical data from ISTAT:

```bash
php artisan istat:geography:import
```

### Models

The package provides three Eloquent models:

#### Region
```php
use PlinCode\IstatGeography\Models\Geography\Region;

$region = Region::where('name', 'Piemonte')->first();
$provinces = $region->provinces;
```

#### Province
```php
use PlinCode\IstatGeography\Models\Geography\Province;

$province = Province::where('code', 'TO')->first();
$municipalities = $province->municipalities;
$region = $province->region;
```

#### Municipality
```php
use PlinCode\IstatGeography\Models\Geography\Municipality;

$municipality = Municipality::where('name', 'Torino')->first();
$province = $municipality->province;
```

### Integration Example in Main Project

If you want to use the package models in your main project, you can extend them:

```php
// app/Models/Region.php
namespace App\Models;

use PlinCode\IstatGeography\Models\Geography\Region as BaseRegion;

class Region extends BaseRegion
{
    // Add your project-specific logic here
    public function customMethod()
    {
        return $this->provinces()->count();
    }
}
```

```php
// app/Models/Province.php
namespace App\Models;

use PlinCode\IstatGeography\Models\Geography\Province as BaseProvince;

class Province extends BaseProvince
{
    // Add your project-specific logic here
}
```

```php
// app/Models/Municipality.php
namespace App\Models;

use PlinCode\IstatGeography\Models\Geography\Municipality as BaseMunicipality;

class Municipality extends BaseMunicipality
{
    // Add your project-specific logic here
}
```

### Replacing Existing Command

If you already have a `geography:import` command in your project, you can replace it with the package's command:

```php
// In app/Console/Kernel.php or in your existing command
Artisan::command('geography:import', function () {
    $this->info('Starting geographical data import...');

    try {
        $count = \PlinCode\IstatGeography\Facades\IstatGeography::import();
        $this->info("Import completed successfully! Imported {$count} municipalities.");
    } catch (\Exception $e) {
        $this->error('Error during import: ' . $e->getMessage());
    }
})->purpose('Import regions, provinces and municipalities from ISTAT');
```

## Configuration

The `config/istat-geography.php` file allows you to customize:

- Table names
- Model classes
- ISTAT CSV URL
- Temporary file name

## Database Structure

### Regions
- `id` (UUID, primary key)
- `name` (string)
- `istat_code` (string, unique)
- `created_at`, `updated_at`, `deleted_at`

### Provinces
- `id` (UUID, primary key)
- `region_id` (UUID, foreign key)
- `name` (string)
- `code` (string, unique)
- `istat_code` (string, unique)
- `created_at`, `updated_at`, `deleted_at`

### Municipalities
- `id` (UUID, primary key)
- `province_id` (UUID, foreign key)
- `name` (string)
- `istat_code` (string, unique)
- `created_at`, `updated_at`, `deleted_at`

## Relationships

- `Region` → `hasMany` → `Province`
- `Province` → `belongsTo` → `Region`
- `Province` → `hasMany` → `Municipality`
- `Municipality` → `belongsTo` → `Province`

## Testing

```bash
composer test
```

## Contributing

1. Fork the project
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
