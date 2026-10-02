<?php

/*
 * Every identifier the Boost guidelines mention is extracted from the rendered
 * file and checked against the real code, so renaming a model, relation,
 * command, option, config key, table or column breaks this test until the
 * guidelines are updated too.
 */

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PlinCode\IstatGeography\Models\Geography\Municipality;

beforeEach(function () {
    $this->guidelines = Blade::render(file_get_contents(__DIR__.'/../../resources/boost/guidelines/core.blade.php'));
    $this->configPath = __DIR__.'/../../config/istat-geography.php';

    preg_match_all('/PlinCode\\\\IstatGeography\\\\[A-Za-z\\\\]*[A-Za-z]/', $this->guidelines, $matches);
    $this->classes = array_values(array_unique($matches[0]));

    // Short names such as `Region` resolve to the FQCNs the guidelines spell out.
    $this->shortNames = collect($this->classes)->mapWithKeys(fn (string $class) => [class_basename($class) => $class])->all();

    // "`FQCN`, table `name`" is the notation the guidelines use to document a model table.
    preg_match_all('/`(PlinCode\\\\[^`]+)`, table `(\w+)`/', $this->guidelines, $matches, PREG_SET_ORDER);
    $this->tables = collect($matches)->mapWithKeys(fn (array $match) => [$match[2] => $match[1]])->all();
});

test('every referenced class exists', function () {
    expect($this->classes)->not->toBeEmpty();

    foreach ($this->classes as $class) {
        expect(class_exists($class))->toBeTrue("{$class} is mentioned in the guidelines but does not exist");
    }
});

test('every referenced static call or relation exists on its class', function () {
    preg_match_all('/\b([A-Z][A-Za-z\\\\]*)::(\w+)\(\)/', $this->guidelines, $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as [, $name, $method]) {
        $class = $this->shortNames[$name] ?? $name;

        expect(class_exists($class))->toBeTrue("{$name} in {$name}::{$method}() does not resolve to a class");

        if (is_subclass_of($class, Facade::class)) {
            $class = get_class($class::getFacadeRoot());
        }

        expect(method_exists($class, $method))->toBeTrue("{$class}::{$method}() is mentioned in the guidelines but does not exist");
    }
});

test('every documented relation has the stated type and related model', function () {
    preg_match_all('/`(\w+)::(\w+)\(\)` (\w+) `(\w+)`/', $this->guidelines, $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as [, $parent, $method, $type, $related]) {
        $relation = (new $this->shortNames[$parent])->{$method}();

        expect($relation)->toBeInstanceOf(Relation::class)
            ->and(class_basename($relation))->toBe(Str::studly($type), "{$parent}::{$method}() is not {$type}")
            ->and($relation->getRelated())->toBeInstanceOf($this->shortNames[$related]);
    }
});

test('a municipality still has no direct region relation', function () {
    expect($this->guidelines)->toContain('A municipality has no direct region relation')
        ->and(method_exists(Municipality::class, 'region'))->toBeFalse();
});

test('every documented table matches its model, the migrations and the config', function () {
    expect($this->tables)->toHaveCount(3);

    $configTables = config('istat-geography.tables');

    foreach ($this->tables as $table => $class) {
        $model = new $class;

        expect($model->getTable())->toBe($table)
            ->and(Schema::connection($model->getConnectionName())->hasTable($table))->toBeTrue("Migrations do not create {$table}")
            ->and($configTables)->toContain($table)
            ->and(config('istat-geography.models'))->toContain($class);
    }
});

test('every referenced table column exists', function () {
    $tables = implode('|', array_keys($this->tables));
    preg_match_all("/`({$tables})\.(\w+)`/", $this->guidelines, $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as [, $table, $column]) {
        $connection = (new $this->tables[$table])->getConnectionName();

        expect(Schema::connection($connection)->hasColumn($table, $column))
            ->toBeTrue("{$table}.{$column} is mentioned in the guidelines but the column does not exist");
    }
});

test('every referenced artisan command is registered', function () {
    preg_match_all('/\bgeography:[a-z-]+/', $this->guidelines, $matches);
    $commands = array_unique($matches[0]);

    expect($commands)->not->toBeEmpty();

    foreach ($commands as $command) {
        expect(array_key_exists($command, Artisan::all()))->toBeTrue("{$command} is mentioned in the guidelines but is not registered");
    }
});

test('every option is defined on the commands it is mentioned with', function () {
    $all = Artisan::all();

    // Each command line in a snippet is a unit, then each paragraph or list item.
    $lines = preg_split('/\n/', $this->guidelines);
    $units = array_filter($lines, fn (string $line) => str_starts_with(trim($line), 'php artisan geography:'));
    $prose = implode("\n", array_diff($lines, $units));
    $units = array_merge($units, preg_split('/\n\s*\n|\n(?=- )/', $prose));

    $checked = 0;

    foreach ($units as $unit) {
        preg_match_all('/\bgeography:[a-z-]+/', $unit, $commands);
        preg_match_all('/(?<![\w-])--([a-z][a-z-]*)/', $unit, $options);

        expect($options[1] === [] || $commands[0] !== [])
            ->toBeTrue('Options mentioned without a command: '.implode(', ', $options[1]));

        foreach (array_unique($commands[0]) as $command) {
            foreach (array_unique($options[1]) as $option) {
                expect($all[$command]->getDefinition()->hasOption($option))
                    ->toBeTrue("{$command} has no --{$option} option");

                $checked++;
            }
        }
    }

    expect($checked)->toBeGreaterThan(0);
});

test('every referenced config key exists in the package config', function () {
    $config = require $this->configPath;

    preg_match_all('/(?<![\w\/-])istat-geography\.([a-z_]+(?:\.[a-z_]+)*)/', $this->guidelines, $matches);
    $keys = array_unique($matches[1]);

    expect($keys)->not->toBeEmpty();

    foreach ($keys as $key) {
        expect(Arr::has($config, $key))->toBeTrue("istat-geography.{$key} is mentioned in the guidelines but is not in the config");
    }
});

test('every referenced environment variable is read by the package config', function () {
    $source = file_get_contents($this->configPath);

    preg_match_all('/`([A-Z][A-Z0-9]*_[A-Z0-9_]+)`/', $this->guidelines, $matches);
    $variables = array_unique($matches[1]);

    expect($variables)->not->toBeEmpty();

    foreach ($variables as $variable) {
        expect($source)->toContain("env('{$variable}'");
    }
});
