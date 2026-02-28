# Changelog

All notable changes to `laravel-istat-geography` will be documented in this file.

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
