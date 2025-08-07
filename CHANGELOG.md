# Changelog

All notable changes to `laravel-istat-geography` will be documented in this file.

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
