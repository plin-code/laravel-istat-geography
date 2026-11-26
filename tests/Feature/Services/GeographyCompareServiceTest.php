<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PlinCode\IstatGeography\Models\Geography\Municipality;
use PlinCode\IstatGeography\Models\Geography\Province;
use PlinCode\IstatGeography\Models\Geography\Region;
use PlinCode\IstatGeography\Services\GeographyCompareService;

beforeEach(function () {
    Storage::disk('local')->delete('istat_municipalities.csv');
});

function mockIstatResponse(string $csvContent): void
{
    Http::fake([
        '*' => Http::response($csvContent, 200),
    ]);
}

function createCsvHeader(): string
{
    return "Codice Regione;Codice dell'Unità territoriale sovracomunale (valida a fini statistici);Codice Provincia (Storico)(1);Progressivo del Comune (2);Codice Comune formato alfanumerico;Denominazione (Italiana e straniera);Denominazione in italiano;Denominazione altra lingua;Codice Ripartizione Geografica;Ripartizione geografica;Denominazione Regione;Denominazione dell'Unità territoriale sovracomunale (valida a fini statistici);Tipologia di Unità territoriale sovracomunale;Flag Comune capoluogo di provincia/città metropolitana/libero consorzio;Sigla automobilistica;Codice Comune formato numerico;Codice Comune numerico con 110 province (dal 2010 al 2016);Codice Comune numerico con 107 province (dal 2006 al 2009);Codice Comune numerico con 103 province (dal 1995 al 2005);Codice Catastale del comune;Codice NUTS1 2021;Codice NUTS2 2021 (3);Codice NUTS3 2021;Codice NUTS1 2024;Codice NUTS2 2024 (3);Codice NUTS3 2024\n";
}

function createCsvRow(
    string $regionCode,
    string $provinceCode,
    string $municipalityCode,
    string $municipalityName,
    string $regionName,
    string $provinceName,
    string $provinceAbbr,
    string $belCode = ''
): string {
    return "{$regionCode};201;{$provinceCode};001;{$municipalityCode};{$municipalityName};{$municipalityName};;1;Nord-ovest;{$regionName};{$provinceName};3;0;{$provinceAbbr};{$municipalityCode};{$municipalityCode};{$municipalityCode};{$municipalityCode};{$belCode};ITC;ITC1;ITC11;ITC;ITC1;ITC11\n";
}

test('compare service identifies new records when database is empty', function () {
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    expect($result->regions->countNew())->toBe(1)
        ->and($result->regions->countModified())->toBe(0)
        ->and($result->regions->countSuppressed())->toBe(0)
        ->and($result->provinces->countNew())->toBe(1)
        ->and($result->provinces->countModified())->toBe(0)
        ->and($result->provinces->countSuppressed())->toBe(0)
        ->and($result->municipalities->countNew())->toBe(1)
        ->and($result->municipalities->countModified())->toBe(0)
        ->and($result->municipalities->countSuppressed())->toBe(0);
});

test('compare service identifies modified records when data differs', function () {
    // Create existing data in DB
    $region = Region::create([
        'name' => 'Old Piemonte',
        'istat_code' => '01',
    ]);

    $province = Province::create([
        'name' => 'Old Torino',
        'code' => 'TO',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    Municipality::create([
        'name' => 'Old Municipality',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    // Mock ISTAT response with different names
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'New Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    // Region name changed
    expect($result->regions->countNew())->toBe(0)
        ->and($result->regions->countModified())->toBe(1)
        ->and($result->regions->modified)->toHaveKey('01')
        ->and($result->regions->modified['01']['changes'])->toHaveKey('name')
        ->and($result->regions->modified['01']['changes']['name']['old'])->toBe('Old Piemonte')
        ->and($result->regions->modified['01']['changes']['name']['new'])->toBe('Piemonte');

    // Province name changed
    expect($result->provinces->countModified())->toBe(1)
        ->and($result->provinces->modified['001']['changes']['name']['old'])->toBe('Old Torino')
        ->and($result->provinces->modified['001']['changes']['name']['new'])->toBe('Torino');

    // Municipality name changed
    expect($result->municipalities->countModified())->toBe(1)
        ->and($result->municipalities->modified['010001']['changes']['name']['old'])->toBe('Old Municipality')
        ->and($result->municipalities->modified['010001']['changes']['name']['new'])->toBe('New Torino');
});

test('compare service identifies suppressed records when absent from ISTAT', function () {
    // Create existing data in DB that won't be in ISTAT
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    $province = Province::create([
        'name' => 'Torino',
        'code' => 'TO',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    // Create a municipality that won't be in ISTAT
    Municipality::create([
        'name' => 'Old Municipality',
        'istat_code' => '010099',
        'province_id' => $province->id,
    ]);

    // Also create one that will be in ISTAT
    Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    // Mock ISTAT response without the old municipality
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    expect($result->municipalities->countSuppressed())->toBe(1)
        ->and($result->municipalities->suppressed)->toHaveKey('010099')
        ->and($result->municipalities->suppressed['010099']['name'])->toBe('Old Municipality');
});

test('compare service uses ISTAT codes as unique keys', function () {
    // Create a record with same name but different ISTAT code
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '99', // Different code
    ]);

    $province = Province::create([
        'name' => 'Torino',
        'code' => 'TO',
        'istat_code' => '999', // Different code
        'region_id' => $region->id,
    ]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    // Should identify as new (by code) and suppressed (old code)
    expect($result->regions->countNew())->toBe(1)
        ->and($result->regions->countSuppressed())->toBe(1)
        ->and($result->regions->new)->toHaveKey('01')
        ->and($result->regions->suppressed)->toHaveKey('99');
});

test('compare service ignores soft-deleted records', function () {
    // Create and soft delete a record
    $region = Region::create([
        'name' => 'Deleted Region',
        'istat_code' => '99',
    ]);
    $region->delete(); // Soft delete

    // Create existing region in DB that matches ISTAT
    Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    // The soft-deleted record (99) should not appear as suppressed
    // Only non-deleted records are considered
    expect($result->regions->countSuppressed())->toBe(0)
        ->and($result->regions->suppressed)->not->toHaveKey('99');
});

test('compare service returns no changes when data matches', function () {
    // Create matching data in DB
    $region = Region::create([
        'name' => 'Piemonte',
        'istat_code' => '01',
    ]);

    // The province code in DB should match what ISTAT data provides at index 14
    // which is the municipality numeric code (010001) based on how GeographyImportService works
    $province = Province::create([
        'name' => 'Torino',
        'code' => 'TO',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);

    Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'province_id' => $province->id,
    ]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    // Verify no changes for each entity type
    expect($result->regions->countNew())->toBe(0)
        ->and($result->regions->countModified())->toBe(0)
        ->and($result->regions->countSuppressed())->toBe(0)
        ->and($result->provinces->countNew())->toBe(0)
        ->and($result->provinces->countModified())->toBe(0)
        ->and($result->provinces->countSuppressed())->toBe(0)
        ->and($result->municipalities->countNew())->toBe(0)
        ->and($result->municipalities->countModified())->toBe(0)
        ->and($result->municipalities->countSuppressed())->toBe(0)
        ->and($result->hasChanges())->toBeFalse();
});

test('compare service handles multiple records correctly', function () {
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Aglie', 'Piemonte', 'Torino', 'TO').
        createCsvRow('01', '001', '010002', 'Airasca', 'Piemonte', 'Torino', 'TO').
        createCsvRow('01', '002', '010003', 'Alessandria', 'Piemonte', 'Alessandria', 'AL')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    expect($result->regions->countNew())->toBe(1) // One region (Piemonte)
        ->and($result->provinces->countNew())->toBe(2) // Two provinces (Torino, Alessandria)
        ->and($result->municipalities->countNew())->toBe(3); // Three municipalities
});

test('entity comparison result correctly reports has changes', function () {
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO')
    );

    $service = app(GeographyCompareService::class);
    $result = $service->compare();

    expect($result->hasChanges())->toBeTrue()
        ->and($result->regions->hasChanges())->toBeTrue()
        ->and($result->provinces->hasChanges())->toBeTrue()
        ->and($result->municipalities->hasChanges())->toBeTrue();
});

function createTorinoHierarchy(?string $belCode): void
{
    $region = Region::create(['name' => 'Piemonte', 'istat_code' => '01']);
    $province = Province::create([
        'name' => 'Torino',
        'code' => 'TO',
        'istat_code' => '001',
        'region_id' => $region->id,
    ]);
    Municipality::create([
        'name' => 'Torino',
        'istat_code' => '010001',
        'bel_code' => $belCode,
        'province_id' => $province->id,
    ]);
}

test('compare service includes bel_code in new municipalities', function () {
    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO', 'L219')
    );

    $result = app(GeographyCompareService::class)->compare();

    expect($result->municipalities->new['010001']['bel_code'])->toBe('L219');
});

test('compare service reports a null bel_code as modified', function () {
    createTorinoHierarchy(null);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO', 'L219')
    );

    $result = app(GeographyCompareService::class)->compare();

    expect($result->municipalities->countModified())->toBe(1)
        ->and($result->municipalities->modified['010001']['changes'])->toBe([
            'bel_code' => ['old' => null, 'new' => 'L219'],
        ]);
});

test('compare service reports a different bel_code as modified', function () {
    createTorinoHierarchy('X000');

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO', 'L219')
    );

    $result = app(GeographyCompareService::class)->compare();

    expect($result->municipalities->modified['010001']['changes'])->toBe([
        'bel_code' => ['old' => 'X000', 'new' => 'L219'],
    ]);
});

test('compare service does not report a matching bel_code', function () {
    createTorinoHierarchy('L219');

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO', 'L219')
    );

    $result = app(GeographyCompareService::class)->compare();

    expect($result->municipalities->countModified())->toBe(0);
});

test('compare service ignores a malformed bel_code in the CSV', function () {
    createTorinoHierarchy('L219');

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO', 'ITC11')
    );

    $result = app(GeographyCompareService::class)->compare();

    expect($result->municipalities->countModified())->toBe(0);
});

test('compare service ignores an empty bel_code in the CSV', function () {
    createTorinoHierarchy('L219');

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO', '')
    );

    $result = app(GeographyCompareService::class)->compare();

    expect($result->municipalities->countModified())->toBe(0);
});

function createSardiniaWithOlbia(?string $belCode = 'G015', bool $withSassari = true): array
{
    $region = Region::create(['name' => 'Sardegna', 'istat_code' => '20']);
    $sassari = $withSassari
        ? Province::create(['name' => 'Sassari', 'code' => 'SS', 'istat_code' => '090', 'region_id' => $region->id])
        : null;
    $olbiaTempio = Province::create(['name' => 'Olbia-Tempio', 'code' => 'OT', 'istat_code' => '104', 'region_id' => $region->id]);
    $olbia = Municipality::create([
        'name' => 'Olbia',
        'istat_code' => '104017',
        'bel_code' => $belCode,
        'province_id' => $olbiaTempio->id,
    ]);

    return [$sassari, $olbiaTempio, $olbia];
}

function olbiaInSassariCsv(): string
{
    return createCsvHeader().
        createCsvRow('20', '090', '090047', 'Olbia', 'Sardegna', 'Sassari', 'SS', 'G015');
}

test('compare service keeps a municipality that moved province, matched by bel_code', function () {
    [$sassari, $olbiaTempio, $olbia] = createSardiniaWithOlbia();
    mockIstatResponse(olbiaInSassariCsv());

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->new)->toBeEmpty()
        ->and($result->suppressed)->toBeEmpty()
        ->and($result->modified)->toHaveKey('090047')
        ->and($result->modified['090047']['id'])->toBe($olbia->id)
        ->and($result->modified['090047']['changes']['istat_code'])->toBe(['old' => '104017', 'new' => '090047'])
        ->and($result->modified['090047']['changes']['province_id'])->toBe(['old' => $olbiaTempio->id, 'new' => $sassari->id]);
});

test('compare service defers the province of a move into a province created by the same update', function () {
    [, , $olbia] = createSardiniaWithOlbia('G015', withSassari: false);
    mockIstatResponse(olbiaInSassariCsv());

    $result = app(GeographyCompareService::class)->compare();

    expect($result->provinces->new)->toHaveKey('090')
        ->and($result->municipalities->new)->toBeEmpty()
        ->and($result->municipalities->modified['090047']['id'])->toBe($olbia->id)
        ->and($result->municipalities->modified['090047']['province_istat_code'])->toBe('090');
});

test('compare service matches a municipality without bel_code by its name', function () {
    [$sassari, , $olbia] = createSardiniaWithOlbia(null);
    mockIstatResponse(olbiaInSassariCsv());

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->new)->toBeEmpty()
        ->and($result->suppressed)->toBeEmpty()
        ->and($result->modified['090047']['id'])->toBe($olbia->id)
        ->and($result->modified['090047']['changes']['bel_code'])->toBe(['old' => null, 'new' => 'G015'])
        ->and($result->modified['090047']['changes']['province_id']['new'])->toBe($sassari->id);
});

test('compare service treats an empty stored bel_code as missing', function () {
    [, , $olbia] = createSardiniaWithOlbia('');
    mockIstatResponse(olbiaInSassariCsv());

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->modified['090047']['id'])->toBe($olbia->id);
});

test('compare service does not match by name a municipality with a different bel_code', function () {
    createSardiniaWithOlbia('X999');
    mockIstatResponse(olbiaInSassariCsv());

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->modified)->toBeEmpty()
        ->and($result->new)->toHaveKey('090047')
        ->and($result->suppressed)->toHaveKey('104017');
});

test('compare service matches homonym municipalities by name and region', function () {
    $piemonte = Region::create(['name' => 'Piemonte', 'istat_code' => '01']);
    $trentino = Region::create(['name' => 'Trentino-Alto Adige', 'istat_code' => '04']);
    $torino = Province::create(['name' => 'Torino', 'code' => 'TO', 'istat_code' => '001', 'region_id' => $piemonte->id]);
    $trento = Province::create(['name' => 'Trento', 'code' => 'TN', 'istat_code' => '022', 'region_id' => $trentino->id]);
    $samonePiemonte = Municipality::create(['name' => 'Samone', 'istat_code' => '001998', 'province_id' => $torino->id]);
    $samoneTrentino = Municipality::create(['name' => 'Samone', 'istat_code' => '022998', 'province_id' => $trento->id]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '001250', 'Samone', 'Piemonte', 'Torino', 'TO', 'H753').
        createCsvRow('04', '022', '022165', 'Samone', 'Trentino-Alto Adige', 'Trento', 'TN', 'H754')
    );

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->new)->toBeEmpty()
        ->and($result->suppressed)->toBeEmpty()
        ->and($result->modified['001250']['id'])->toBe($samonePiemonte->id)
        ->and($result->modified['022165']['id'])->toBe($samoneTrentino->id);
});

test('compare service leaves ambiguous homonyms unmatched', function () {
    $piemonte = Region::create(['name' => 'Piemonte', 'istat_code' => '01']);
    $torino = Province::create(['name' => 'Torino', 'code' => 'TO', 'istat_code' => '001', 'region_id' => $piemonte->id]);
    Municipality::create(['name' => 'Samone', 'istat_code' => '001998', 'province_id' => $torino->id]);
    Municipality::create(['name' => 'Samone', 'istat_code' => '001999', 'province_id' => $torino->id]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '001250', 'Samone', 'Piemonte', 'Torino', 'TO', 'H753')
    );

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->modified)->toBeEmpty()
        ->and($result->new)->toHaveKey('001250')
        ->and($result->suppressed)->toHaveKeys(['001998', '001999']);
});

test('compare service skips a move whose new istat_code belongs to a soft deleted municipality', function () {
    [$sassari, $olbiaTempio, $olbia] = createSardiniaWithOlbia();
    Municipality::create(['name' => 'Old Olbia', 'istat_code' => '090047', 'province_id' => $sassari->id])->delete();
    mockIstatResponse(olbiaInSassariCsv());

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->modified)->toHaveKey('104017')
        ->and($result->modified['104017']['changes'])->not->toHaveKey('istat_code')
        ->and($result->modified['104017']['changes']['province_id'])->toBe(['old' => $olbiaTempio->id, 'new' => $sassari->id])
        ->and($result->new)->toBeEmpty()
        ->and($result->suppressed)->toBeEmpty()
        ->and($result->skipped['090047'])->toBe(['id' => $olbia->id, 'name' => 'Olbia', 'from' => '104017', 'to' => '090047']);
});

test('compare service does not match by name alone a name that has homonyms', function () {
    $piemonte = Region::create(['name' => 'Piemonte', 'istat_code' => '01']);
    $trentino = Region::create(['name' => 'Trentino-Alto Adige', 'istat_code' => '04']);
    $torino = Province::create(['name' => 'Torino', 'code' => 'TO', 'istat_code' => '001', 'region_id' => $piemonte->id]);
    $trento = Province::create(['name' => 'Trento', 'code' => 'TN', 'istat_code' => '022', 'region_id' => $trentino->id]);
    Municipality::create(['name' => 'Samone', 'istat_code' => '001998', 'province_id' => $torino->id]);
    Municipality::create(['name' => 'Samone', 'istat_code' => '022165', 'bel_code' => 'H754', 'province_id' => $trento->id]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('04', '022', '022165', 'Samone', 'Trentino-Alto Adige', 'Trento', 'TN', 'H754').
        createCsvRow('03', '015', '015999', 'Samone', 'Lombardia', 'Milano', 'MI', 'Z999')
    );

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->modified)->toBeEmpty()
        ->and($result->new)->toHaveKey('015999')
        ->and($result->suppressed)->toHaveKey('001998');
});

test('compare service creates a new municipality reusing the code of a moving one after the move', function () {
    [, , $olbia] = createSardiniaWithOlbia();
    mockIstatResponse(
        olbiaInSassariCsv().
        createCsvRow('20', '104', '104017', 'Newtown', 'Sardegna', 'Olbia-Tempio', 'OT', 'H111')
    );

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->skipped)->toBeEmpty()
        ->and($result->modified['090047']['id'])->toBe($olbia->id)
        ->and($result->new['104017']['waits_for_moved_code'])->toBeTrue();
});

test('compare service orders chained moves so that each code is left before it is reused', function () {
    createTorinoHierarchy('L219');
    $province = Province::where('istat_code', '001')->first();
    $first = Municipality::create(['name' => 'First', 'istat_code' => '001002', 'bel_code' => 'A001', 'province_id' => $province->id]);
    $second = Municipality::create(['name' => 'Second', 'istat_code' => '001003', 'bel_code' => 'A002', 'province_id' => $province->id]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO', 'L219').
        createCsvRow('01', '001', '001002', 'Second', 'Piemonte', 'Torino', 'TO', 'A002').
        createCsvRow('01', '001', '001004', 'First', 'Piemonte', 'Torino', 'TO', 'A001')
    );

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->skipped)->toBeEmpty()
        ->and(array_map('strval', array_keys($result->modified)))->toBe(['001004', '001002'])
        ->and($result->modified['001004']['id'])->toBe($first->id)
        ->and($result->modified['001002']['id'])->toBe($second->id);
});

test('compare service skips municipalities swapping their codes', function () {
    createTorinoHierarchy('L219');
    $province = Province::where('istat_code', '001')->first();
    Municipality::create(['name' => 'First', 'istat_code' => '001002', 'bel_code' => 'A001', 'province_id' => $province->id]);
    Municipality::create(['name' => 'Second', 'istat_code' => '001003', 'bel_code' => 'A002', 'province_id' => $province->id]);

    mockIstatResponse(
        createCsvHeader().
        createCsvRow('01', '001', '010001', 'Torino', 'Piemonte', 'Torino', 'TO', 'L219').
        createCsvRow('01', '001', '001003', 'First', 'Piemonte', 'Torino', 'TO', 'A001').
        createCsvRow('01', '001', '001002', 'Second', 'Piemonte', 'Torino', 'TO', 'A002')
    );

    $result = app(GeographyCompareService::class)->compare()->municipalities;

    expect($result->modified)->toBeEmpty()
        ->and($result->new)->toBeEmpty()
        ->and($result->suppressed)->toBeEmpty()
        ->and(array_map('strval', array_keys($result->skipped)))->toEqualCanonicalizing(['001002', '001003']);
});
