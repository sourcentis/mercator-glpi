<?php

use App\Services\Glpi\Mappers\LogicalServerMapper;
use App\Services\Glpi\Mappers\WorkstationMapper;

// ── LogicalServerMapper hérite du CPU/OS de WorkstationMapper ───────────────
// Régression pour l'issue #11 (CPU non remonté) : LogicalServerMapper délègue
// entièrement à WorkstationMapper, donc le correctif doit s'appliquer sans
// modification supplémentaire ici.

it('mappe le CPU et l\'OS via WorkstationMapper', function () {
    $payload = (new LogicalServerMapper(new WorkstationMapper))->map(
        glpiComputer(),
        ['buildings_map' => [], 'sites_map' => []]
    );

    expect($payload['cpu'])->toBe('Intel Core i7-1165G7 — 2800 MHz — 4 cœurs');
    expect($payload['operating_system'])->toBe('Windows 11 Pro — 23H2');
});

// ── Issue #19 : date de mise en service Infocom → install_date ──────────────

it('mappe la date de mise en service Infocom (use_date) sur install_date', function () {
    $payload = (new LogicalServerMapper(new WorkstationMapper))->map(
        ['id' => 42, 'name' => 'VM-01', '_infocoms' => ['use_date' => '2023-04-12']],
        ['buildings_map' => [], 'sites_map' => []]
    );

    expect($payload['install_date'])->toBe('2023-04-12');
});

it('n\'envoie pas install_date quand la date de mise en service est absente ou vide', function (array $infocoms) {
    $payload = (new LogicalServerMapper(new WorkstationMapper))->map(
        ['id' => 42, 'name' => 'VM-01', '_infocoms' => $infocoms],
        ['buildings_map' => [], 'sites_map' => []]
    );

    expect(array_key_exists('install_date', $payload))->toBeFalse();
})->with([
    'pas d\'Infocom' => [[]],
    'use_date null' => [['use_date' => null]],
    'use_date vide' => [['use_date' => '']],
    'use_date 0000-00-00' => [['use_date' => '0000-00-00']],
]);
