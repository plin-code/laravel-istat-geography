<?php

declare(strict_types=1);

/**
 * Builds the municipality coordinates dataset published as a GitHub release asset.
 *
 * One representative point per municipality is computed from the ISTAT administrative
 * boundaries redistributed by Zornade (CC BY 4.0). The point is the polygon centroid when
 * the centroid falls inside the polygon, otherwise GEOS PointOnSurface, so it is always
 * inside the municipality. Both are computed in ETRS89 LAEA (EPSG:3035) and converted
 * back to WGS 84 (EPSG:4326).
 *
 * Requirements: PHP 8.3+ with pdo_sqlite and zlib, Docker (runs the official GDAL image).
 *
 * Usage:
 *   php scripts/build-coordinates-dataset.php [options]
 *
 * Options:
 *   --gpkg=<path>        Use a local boundaries GeoPackage instead of downloading it
 *   --istat-csv=<path>   Use a local ISTAT municipalities CSV instead of downloading it
 *   --output=<path>      Output file (default: build/municipality_coordinates_dataset.json.gz)
 *   --spot-check         Reverse geocode a few points with the Zornade API to check that
 *                        each point falls in the expected municipality. Reads the API
 *                        token from the ZORNADE_TOKEN environment variable.
 */
const BOUNDARIES_URL = 'https://wupqwfqjfpwrapgnogjv.supabase.co/storage/v1/object/public/parcel-data-access/confini-amministrativi/confini-amministrativi.gpkg';
const ISTAT_CSV_URL = 'https://www.istat.it/storage/codici-unita-amministrative/Elenco-comuni-italiani.csv';
const ZORNADE_REVERSE_URL = 'https://api.zornade.com/api/v2/geocode/reverse';
const GDAL_IMAGE = 'ghcr.io/osgeo/gdal:ubuntu-small-3.13.2';

/**
 * Municipalities established by a merger after the boundaries dataset was published.
 * Their point is computed on the union of the predecessors' polygons.
 *
 * @var array<string, list<string>>
 */
const MERGED_MUNICIPALITIES = [
    '024128' => ['024044', '024103'], // Sovizzo (Gambugliano + Sovizzo)
    '025075' => ['025002', '025070'], // Setteville (Alano di Piave + Quero Vas)
    '028108' => ['028022', '028098'], // Santa Caterina d'Este (Carceri + Vighizzolo d'Este)
];

/**
 * Municipalities reverse geocoded by --spot-check: large, small, irregular and insular ones.
 *
 * @var list<string>
 */
const SPOT_CHECK_CODES = ['058091', '037006', '015146', '039014', '065011', '083041', '084020', '021085', '043042', '092003'];

$root = dirname(__DIR__);
$workDir = $root.'/build/coordinates';
$options = getopt('', ['gpkg:', 'istat-csv:', 'output:', 'spot-check']);

try {
    if (! is_dir($workDir) && ! mkdir($workDir, 0777, true)) {
        throw new RuntimeException("Unable to create the work directory {$workDir}");
    }

    $gpkgPath = $options['gpkg'] ?? $workDir.'/confini-amministrativi.gpkg';
    $istatCsvPath = $options['istat-csv'] ?? $workDir.'/Elenco-comuni-italiani.csv';
    $outputPath = $options['output'] ?? $root.'/build/municipality_coordinates_dataset.json.gz';

    if (! isset($options['gpkg'])) {
        download(BOUNDARIES_URL, $gpkgPath);
    }

    if (! isset($options['istat-csv'])) {
        download(ISTAT_CSV_URL, $istatCsvPath);
    }

    $istat = readIstatMunicipalities($istatCsvPath);
    $boundaryCodes = readBoundaryCodes($gpkgPath);
    info(sprintf('ISTAT municipalities: %d, boundary polygons: %d', count($istat), count($boundaryCodes)));

    assertMergersAreResolvable($istat, $boundaryCodes);

    $points = computePoints($gpkgPath, $workDir);

    $municipalities = [];
    $unmatched = [];

    foreach ($istat as $istatCode => $municipality) {
        // PHP turns numeric string keys such as "103001" into integers.
        $istatCode = (string) $istatCode;

        if (! isset($points[$istatCode])) {
            $unmatched[] = "{$istatCode} {$municipality['name']}";

            continue;
        }

        $municipalities[] = [
            'istat_code' => $istatCode,
            'bel_code' => $municipality['bel_code'],
            'name' => $municipality['name'],
            'latitude' => $points[$istatCode]['latitude'],
            'longitude' => $points[$istatCode]['longitude'],
            'method' => $points[$istatCode]['method'],
        ];
    }

    $obsolete = array_map('strval', array_diff(array_keys($points), array_keys($istat)));

    if ($unmatched !== []) {
        throw new RuntimeException('Municipalities without a point: '.implode(', ', $unmatched));
    }

    $methods = array_count_values(array_column($municipalities, 'method'));
    ksort($methods);

    $dataset = [
        'meta' => [
            'name' => 'Italian municipalities representative points',
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'count' => count($municipalities),
            'crs' => 'EPSG:4326',
            'method' => 'Polygon centroid when it falls inside the municipality, otherwise PointOnSurface. Computed in EPSG:3035 with GDAL/SpatiaLite.',
            'methods' => $methods,
            'source' => [
                'name' => 'Confini amministrativi (ISTAT), redistributed by Zornade',
                'url' => 'https://zornade.com/data-downloads/',
                'download_url' => BOUNDARIES_URL,
                'last_modified' => lastModified(BOUNDARIES_URL),
            ],
            'istat_list' => [
                'url' => ISTAT_CSV_URL,
                'downloaded_at' => gmdate('Y-m-d', (int) filemtime($istatCsvPath)),
            ],
            'merged_municipalities' => MERGED_MUNICIPALITIES,
            'license' => 'CC BY 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
            'attribution' => 'Source: ISTAT, administrative boundaries (CC BY 4.0). Data processed by Zornade (https://zornade.com). Representative points derived by plin-code/laravel-istat-geography.',
        ],
        'municipalities' => $municipalities,
    ];

    $json = json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $gzip = gzencode($json, 9);

    if ($gzip === false || file_put_contents($outputPath, $gzip) === false) {
        throw new RuntimeException("Unable to write {$outputPath}");
    }

    info(sprintf('Written %s (%d municipalities, %s KB)', $outputPath, count($municipalities), number_format(strlen($gzip) / 1024, 1)));
    info('Methods: '.json_encode($methods));
    info('Merged municipalities computed from predecessors: '.implode(', ', array_keys(MERGED_MUNICIPALITIES)));
    info('Polygons dropped because their code is no longer in the ISTAT list: '.($obsolete === [] ? 'none' : implode(', ', $obsolete)));

    if (isset($options['spot-check'])) {
        spotCheck($municipalities);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: '.$exception->getMessage().PHP_EOL);

    exit(1);
}

function info(string $message): void
{
    fwrite(STDOUT, $message.PHP_EOL);
}

function download(string $url, string $path): void
{
    if (is_file($path) && date('Y-m-d') === date('Y-m-d', (int) filemtime($path))) {
        info("Using {$path} downloaded today");

        return;
    }

    info("Downloading {$url}");

    if (! copy($url, $path.'.part') || ! rename($path.'.part', $path)) {
        throw new RuntimeException("Download failed: {$url}");
    }
}

function lastModified(string $url): ?string
{
    $headers = get_headers($url, true, stream_context_create(['http' => ['method' => 'HEAD']]));

    if ($headers === false) {
        throw new RuntimeException("Unable to read the headers of {$url}");
    }

    $headers = array_change_key_case($headers);
    $value = $headers['last-modified'] ?? null;

    if (is_array($value)) {
        $value = end($value);
    }

    return is_string($value) ? gmdate('Y-m-d\TH:i:s\Z', (int) strtotime($value)) : null;
}

/**
 * @return array<string, array{name: string, bel_code: string}>
 */
function readIstatMunicipalities(string $path): array
{
    $handle = fopen($path, 'r');

    if ($handle === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    fgetcsv($handle, null, ';', '"', '');
    $municipalities = [];

    while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
        if (count($row) < 20) {
            continue;
        }

        $row = array_map(fn (?string $value): string => mb_convert_encoding((string) $value, 'UTF-8', 'ISO-8859-15'), $row);
        $municipalities[$row[4]] = ['name' => $row[6], 'bel_code' => $row[19]];
    }

    fclose($handle);

    if (count($municipalities) < 7000) {
        throw new RuntimeException('The ISTAT CSV looks incomplete: '.count($municipalities).' municipalities');
    }

    ksort($municipalities, SORT_STRING);

    return $municipalities;
}

/**
 * @return list<string>
 */
function readBoundaryCodes(string $gpkgPath): array
{
    $pdo = new PDO('sqlite:'.$gpkgPath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    return $pdo->query('SELECT pro_com_t FROM comuni')->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * @param  array<string, array{name: string, bel_code: string}>  $istat
 * @param  list<string>  $boundaryCodes
 */
function assertMergersAreResolvable(array $istat, array $boundaryCodes): void
{
    $withoutPolygon = array_diff(array_keys($istat), $boundaryCodes, array_keys(MERGED_MUNICIPALITIES));

    if ($withoutPolygon !== []) {
        throw new RuntimeException('ISTAT municipalities without a polygon, add them to MERGED_MUNICIPALITIES: '.implode(', ', $withoutPolygon));
    }

    $missingPredecessors = array_diff(array_merge(...array_values(MERGED_MUNICIPALITIES)), $boundaryCodes);

    if ($missingPredecessors !== []) {
        throw new RuntimeException('Predecessors missing from the boundaries: '.implode(', ', $missingPredecessors));
    }
}

/**
 * @return array<string, array{latitude: float, longitude: float, method: string}>
 */
function computePoints(string $gpkgPath, string $workDir): array
{
    $predecessors = array_merge(...array_values(MERGED_MUNICIPALITIES));
    $quote = fn (string $code): string => "'{$code}'";

    $sources = ['SELECT pro_com_t AS istat_code, geom FROM comuni WHERE pro_com_t NOT IN ('.implode(',', array_map($quote, $predecessors)).')'];

    foreach (MERGED_MUNICIPALITIES as $code => $codes) {
        $sources[] = "SELECT '{$code}' AS istat_code, ST_Union(geom) AS geom FROM comuni WHERE pro_com_t IN (".implode(',', array_map($quote, $codes)).')';
    }

    $sql = 'WITH projected AS (SELECT istat_code, ST_Transform(geom, 3035) AS g FROM ('.implode(' UNION ALL ', $sources).')),'
        .' candidates AS (SELECT istat_code, g, ST_Centroid(g) AS c FROM projected),'
        .' points AS (SELECT istat_code,'
        ." CASE WHEN ST_Within(c, g) THEN 'centroid' ELSE 'point_on_surface' END AS method,"
        .' ST_Transform(CASE WHEN ST_Within(c, g) THEN c ELSE ST_PointOnSurface(g) END, 4326) AS p FROM candidates)'
        ." SELECT istat_code, method, printf('%.7f', ST_Y(p)) AS latitude, printf('%.7f', ST_X(p)) AS longitude FROM points ORDER BY istat_code";

    $csvName = 'points.csv';
    @unlink($workDir.'/'.$csvName);

    $command = sprintf(
        'docker run --rm -v %s:/gpkg:ro -v %s:/out %s ogr2ogr -f CSV %s /gpkg/%s -dialect SQLite -sql %s 2>&1',
        escapeshellarg(dirname((string) realpath($gpkgPath))),
        escapeshellarg((string) realpath($workDir)),
        GDAL_IMAGE,
        escapeshellarg('/out/'.$csvName),
        escapeshellarg(basename($gpkgPath)),
        escapeshellarg($sql),
    );

    info('Computing points with '.GDAL_IMAGE.' (takes a few minutes)');
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("ogr2ogr failed ({$exitCode}): ".implode(PHP_EOL, $output));
    }

    $handle = fopen($workDir.'/'.$csvName, 'r');

    if ($handle === false) {
        throw new RuntimeException('ogr2ogr did not write the points CSV');
    }

    $header = fgetcsv($handle, null, ',', '"', '');
    $points = [];

    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $record = array_combine($header, $row);
        $latitude = (float) $record['latitude'];
        $longitude = (float) $record['longitude'];

        if ($latitude < 35 || $latitude > 48 || $longitude < 6 || $longitude > 19) {
            throw new RuntimeException("Point outside Italy for {$record['istat_code']}: {$latitude}, {$longitude}");
        }

        $points[$record['istat_code']] = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'method' => $record['method'],
        ];
    }

    fclose($handle);

    return $points;
}

/**
 * @param  list<array{istat_code: string, bel_code: string, name: string, latitude: float, longitude: float, method: string}>  $municipalities
 */
function spotCheck(array $municipalities): void
{
    $token = getenv('ZORNADE_TOKEN');

    if (! is_string($token) || $token === '') {
        throw new RuntimeException('--spot-check needs the ZORNADE_TOKEN environment variable');
    }

    $byCode = array_column($municipalities, null, 'istat_code');
    $context = stream_context_create(['http' => ['header' => "X-API-Key: {$token}\r\n", 'timeout' => 30, 'ignore_errors' => true]]);

    info('Spot check (nearest address to each point, Zornade reverse geocoding):');

    foreach (SPOT_CHECK_CODES as $code) {
        $municipality = $byCode[$code] ?? null;

        if ($municipality === null) {
            info("  {$code}: not in the dataset");

            continue;
        }

        $url = ZORNADE_REVERSE_URL.'?'.http_build_query(['lat' => $municipality['latitude'], 'lng' => $municipality['longitude'], 'radius' => 500]);
        $body = file_get_contents($url, false, $context);
        $address = is_string($body) ? (json_decode($body, true)['data'][0] ?? null) : null;

        if (! is_array($address)) {
            info("  {$code} {$municipality['name']} ({$municipality['method']}): no address within 500 m");

            continue;
        }

        info(sprintf(
            '  %s %s (%s): %s, %s m, %s',
            $code,
            $municipality['name'],
            $municipality['method'],
            $address['municipality_code'] === $municipality['bel_code'] ? 'same municipality' : "nearest address in {$address['municipality_name']}",
            round((float) $address['distance_meters']),
            $address['formatted_address'],
        ));
    }
}
