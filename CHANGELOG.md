# Changelog

All notable changes to `laravel-istat-geography` will be documented in this file.

## v1.2.0 - CAP (Postal Code) Support - 2026-03-19

### 📮 CAP (Postal Code) Support

This release adds full postal code import support to laravel-istat-geography.

#### What's new

New fields on municipalities table

- `bel_code` - Codice Catastale, used to match municipalities with CAP data
- `postal_code` - primary postal code (CAP)
- `postal_codes` - range of postal codes for large municipalities (e.g. 00118-00199)

#### New Artisan commands

- `geography:import --cap` - imports ISTAT data and postal codes in one shot
- `geography:import --cap-only` - updates only CAP on existing municipalities
- `geography:import --cap-file=<path>` - uses a local JSON file instead of downloading
- `geography:download-cap` - downloads and decompresses the CAP GeoJSON locally for offline use

#### New migration

`extend_municipalities_with_postal_codes`, run `php artisan migrate` to apply.

#### Recommended usage

```bash
# First time: import everything
php artisan geography:import --cap --cap-file=cap-dataset.json

# Update only CAP on existing data
php artisan geography:import --cap-only --cap-file=cap-dataset.json

```
> The remote **GeoJSON** with geometries is ~464 MB. Using --cap-file with the preprocessed properties-only dataset (~3 MB) is strongly recommended.

#### Data source

Postal code data is sourced from [Zornade Data Downloads](https://zornade.com/data-downloads/). A huge thanks to [Zornade](https://github.com/zornade) for their incredible work in making Italian public data freely available as open data. 🙏

## ISTAT GeoJSON Full v1 - 2026-03-17

Full GeoJSON (RFC 7946) with municipality boundaries from OpenStreetMap. Includes polygon geometries for all Italian municipalities.

## ISTAT Properties Data v1 - 2026-03-17

Lightweight JSON with municipality properties only. No geometries included.

## v1.1.0 - Geography update command - 2026-02-28

### Added

- New `geography:update` Artisan command for incremental synchronization of geographical data with ISTAT
  
  - Adds new records (regions, provinces, municipalities) found in ISTAT data
  - Updates modified records (name changes, code changes, relationship changes)
  - Soft-deletes records no longer present in ISTAT data
  - `--dry-run` option to simulate changes without modifying the database
  - `--force` option to continue past non-critical errors
  - Progressive verbosity levels (`-v`, `-vv`, `-vvv`) for detailed output
  - Database transaction wrapping for atomic updates with automatic rollback on failure
  - Progress bar display during operations (with `-v`)
  
- New `GeographyCompareService` for comparing ISTAT CSV data against existing database records
  
  - Detects new, modified, and suppressed records across all entity types
  - Uses ISTAT codes as unique identifiers for comparison
  - Resolves foreign key relationships (region → province → municipality)
  - Ignores soft-deleted records during comparison
  - Daily CSV caching to avoid redundant downloads
  
- New `GeographyUpdateService` for applying detected changes to the database
  
  - Creates new records with proper parent-child relationship linking
  - Updates only ISTAT-sourced fields, preserving custom fields
  - Soft-deletes suppressed records without cascading to child entities
  - Supports ISTAT code-based lookup for newly created parent records
  
- New `ComparisonResult` and `EntityComparisonResult` value objects for structured comparison data
  
- New `istatFields()` static method on `Region`, `Province`, and `Municipality` models
  
  - Returns the list of fields that can be safely overwritten by the update command
  

## v1.0.1 - Readme review - 2025-08-07

Review typos in README file and add art.

## v1.0.0 - Initial Release - 2025-08-07

### 🎉 Initial Release

This is the first stable release of the Laravel ISTAT Geography package.

#### ✨ Features

- Import Italian geographical data from ISTAT (regions, provinces, municipalities)
- Automatic CSV download and caching (daily cache)
- Eloquent models with relationships
- Configurable table names and model classes
- Artisan command for easy data import
- Soft deletes support
- UUID primary keys

#### 🚀 Installation

```bash
composer require plin-code/laravel-istat-geography






```
#### 🔧 Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="istat-geography-config"






```
#### 📦 Usage

Import geographical data:

```bash
php artisan istat:geography:import






```
#### 🏠 Architecture

- **Models**: Region, Province, Municipality with proper relationships
- **Service**: GeographyImportService handles CSV processing and import
- **Command**: IstatGeographyCommand for CLI usage
- **Config**: Fully configurable via `config/istat-geography.php`
