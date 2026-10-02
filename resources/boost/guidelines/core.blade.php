## Laravel ISTAT Geography

This package ships the Italian regions, provinces and municipalities published
by ISTAT as Eloquent models, migrations and Artisan import commands. Use these
models. Do not create your own `regions`, `provinces` or `municipalities`
tables, models or seeders, and do not hardcode Italian place lists.

### Models and tables

All three models use UUID string primary keys (`HasUuids`), `SoftDeletes` and
factories, and run on the connection set in `istat-geography.connection`.

- `PlinCode\IstatGeography\Models\Geography\Region`, table `regions`.
  Key columns: `regions.name`, `regions.istat_code` (2 chars, unique).
- `PlinCode\IstatGeography\Models\Geography\Province`, table `provinces`.
  Key columns: `provinces.name`, `provinces.region_id`, `provinces.code`
  (2 letter abbreviation such as `TO`, unique), `provinces.istat_code`
  (3 chars, unique).
- `PlinCode\IstatGeography\Models\Geography\Municipality`, table `municipalities`.
  Key columns: `municipalities.name`, `municipalities.province_id`,
  `municipalities.istat_code` (6 chars, unique), `municipalities.bel_code`
  (4 chars, cadastral code, nullable), `municipalities.postal_code` (main CAP),
  `municipalities.postal_codes` (CAP range of a multi CAP municipality, such as
  `00118-00199`),
  `municipalities.latitude` and `municipalities.longitude` (nullable, cast to
  float). CAP and coordinates stay empty until imported.

ISTAT codes are strings with leading zeros (`'01'`, `'058091'`). Never cast
them to integers. Look records up by `istat_code`, not by `name`.

### Relations

- `Region::provinces()` hasMany `Province`.
- `Province::region()` belongsTo `Region`.
- `Province::municipalities()` hasMany `Municipality`.
- `Municipality::province()` belongsTo `Province`.

Each relation returns the class configured under `istat-geography.models`, so
a custom model is what you get back.

A municipality has no direct region relation, go through its province.

@verbatim
<code-snippet name="Query the geography" lang="php">
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;

$rome = Municipality::with('province.region')->where('istat_code', '058091')->first();
$rome->province->region->name; // Lazio

$turin = Province::where('code', 'TO')->first()->municipalities;
</code-snippet>
@endverbatim

`Region::istatFields()`, `Province::istatFields()` and
`Municipality::istatFields()` list the columns `geography:update` may
overwrite. `Municipality::capFields()` and `Municipality::coordinateFields()`
are filled only by the CAP and coordinates imports. Columns you add to
extended models are never touched.

### Importing and updating the data

@verbatim
<code-snippet name="Load and refresh the data" lang="bash">
php artisan migrate
php artisan geography:import --cap --coordinates
php artisan geography:update --dry-run
php artisan geography:update
</code-snippet>
@endverbatim

- `geography:import` downloads the ISTAT CSV and upserts by `istat_code`.
  `--cap` and `--coordinates` also import postal codes and coordinates.
  `--cap-only` and `--coordinates-only` skip the ISTAT data and fill existing
  municipalities. `--cap-file=` and `--coordinates-file=` read a local file
  instead of downloading.
- `geography:update` adds new records, updates changed ISTAT fields and soft
  deletes records ISTAT removed, in one transaction. `--dry-run` only reports,
  `--force` continues on non critical errors. It never overwrites CAP or
  coordinates.
- `geography:download-cap` and `geography:download-coordinates` save the
  datasets locally, both accept `--url=` and `--output=`.

The facade `PlinCode\IstatGeography\Facades\IstatGeography::import()` runs the
ISTAT import from code.

### Configuration

Read `config/istat-geography.php` before assuming names:

- `istat-geography.connection` (`ISTAT_DB_CONNECTION`): the connection of the
  three tables, the default connection when empty.
- `istat-geography.models.region`, `istat-geography.models.province`,
  `istat-geography.models.municipality`: the classes the import and update
  services write through and the classes the package relations return. Point
  them at your own models extending the package ones.
- `istat-geography.tables.regions`, `istat-geography.tables.provinces`,
  `istat-geography.tables.municipalities`: the table names used by the
  migrations and the models. Set them before running the migrations, changing
  them later does not rename existing tables. A `$table` set on your own model
  still wins.
- `istat-geography.coordinates.enabled` (`ISTAT_IMPORT_COORDINATES`): import
  coordinates on every `geography:import`.
- `istat-geography.import.csv_url` (`ISTAT_CSV_URL`): the ISTAT CSV source.
