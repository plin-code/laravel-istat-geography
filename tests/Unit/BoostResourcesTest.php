<?php

use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    $this->guidelinesPath = __DIR__.'/../../resources/boost/guidelines/core.blade.php';
});

test('ships a single core guidelines file', function () {
    expect(file_exists($this->guidelinesPath))->toBeTrue()
        ->and(glob(dirname($this->guidelinesPath).'/*'))->toHaveCount(1);
});

test('guidelines render as blade without leaving directives behind', function () {
    $rendered = Blade::render(file_get_contents($this->guidelinesPath));

    expect($rendered)->not->toContain('@verbatim')
        ->and($rendered)->not->toContain('@endverbatim')
        ->and($rendered)->not->toContain('{{');
});

test('guidelines tell agents to use the package models instead of their own tables', function () {
    $rendered = Blade::render(file_get_contents($this->guidelinesPath));

    expect($rendered)->toContain('Use these')
        ->toContain('Do not create your own');
});

test('guidelines name the models, the import commands and the config keys', function () {
    $rendered = Blade::render(file_get_contents($this->guidelinesPath));

    expect($rendered)->toContain('PlinCode\IstatGeography\Models\Geography\Region')
        ->toContain('PlinCode\IstatGeography\Models\Geography\Province')
        ->toContain('PlinCode\IstatGeography\Models\Geography\Municipality')
        ->toContain('geography:import')
        ->toContain('geography:update')
        ->toContain('--coordinates')
        ->toContain('istat-geography.tables')
        ->toContain('istat-geography.models');
});

test('guidelines wrap their code examples in code-snippet tags', function () {
    $rendered = Blade::render(file_get_contents($this->guidelinesPath));

    expect($rendered)->toContain('<code-snippet')
        ->toContain('</code-snippet>')
        ->and(substr_count($rendered, '<code-snippet'))->toBe(substr_count($rendered, '</code-snippet>'));
});

test('guidelines stay short enough to keep in context', function () {
    $rendered = Blade::render(file_get_contents($this->guidelinesPath));

    expect(strlen($rendered))->toBeLessThan(6000);
});

test('composer suggests laravel boost without requiring it', function () {
    $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composer['suggest']['laravel/boost'] ?? null)->toBeString()
        ->not->toBe('')
        ->and($composer['require']['laravel/boost'] ?? null)->toBeNull()
        ->and($composer['require-dev']['laravel/boost'] ?? null)->toBeNull();
});
