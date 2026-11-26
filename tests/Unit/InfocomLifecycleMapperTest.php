<?php

use App\Services\Glpi\Mappers\NetworkDeviceMapper;
use App\Services\Glpi\Mappers\PeripheralMapper;
use App\Services\Glpi\Mappers\PhoneMapper;
use App\Services\Glpi\Mappers\PhysicalSecurityDeviceMapper;
use App\Services\Glpi\Mappers\RouterMapper;
use App\Services\Glpi\Mappers\StorageDeviceMapper;
use App\Services\Glpi\Mappers\WifiTerminalMapper;

// ── Issue #19 : cycle de vie Infocom sur tous les types d'équipements ──────────

function glpiEquipmentWithInfocom(array $infocoms): array
{
    return [
        'id' => 7,
        'name' => 'EQ-01',
        'serial' => 'SN-0001',
        'manufacturers_id' => 'Cisco',
        'locations_id' => 0,
        '_infocoms' => $infocoms,
    ];
}

dataset('equipment mappers', [
    'Peripheral' => [fn () => new PeripheralMapper],
    'Phone' => [fn () => new PhoneMapper],
    'NetworkDevice (physical-switches)' => [fn () => new NetworkDeviceMapper],
    'Router' => [fn () => new RouterMapper],
    'PhysicalSecurityDevice' => [fn () => new PhysicalSecurityDeviceMapper],
    'WifiTerminal' => [fn () => new WifiTerminalMapper],
    'StorageDevice' => [fn () => new StorageDeviceMapper],
]);

it('mappe mise en service, fin de garantie, réforme et n° de série', function (Closure $mapper) {
    $payload = $mapper()->map(glpiEquipmentWithInfocom([
        'use_date' => '2021-09-30 00:00:00',
        'warranty_date' => '2021-10-01',
        'warranty_duration' => 60,
        'decommission_date' => '2030-12-31',
    ]), ['buildings_map' => [], 'sites_map' => []]);

    expect($payload)
        ->toMatchArray([
            'install_date' => '2021-09-30',
            'warranty_end_date' => '2026-10-01',
            'end_of_life_date' => '2030-12-31',
            'serial_number' => 'SN-0001',
        ])
        ->and($payload['description'] ?? '')->not->toContain('SN-0001');
})->with('equipment mappers');

it('n\'envoie aucune date Infocom absente ou à 0000-00-00', function (Closure $mapper) {
    $payload = $mapper()->map(glpiEquipmentWithInfocom([
        'use_date' => '0000-00-00',
        'warranty_date' => null,
        'warranty_duration' => 36,
        'decommission_date' => '',
    ]), ['buildings_map' => [], 'sites_map' => []]);

    expect($payload)
        ->not->toHaveKey('install_date')
        ->not->toHaveKey('warranty_end_date')
        ->not->toHaveKey('end_of_life_date');
})->with('equipment mappers');

it('calcule la fin de garantie comme GLPI (strtotime +N month)', function () {
    $payload = (new PhoneMapper)->map(glpiEquipmentWithInfocom([
        'warranty_date' => '2024-02-29',
        'warranty_duration' => 12,
    ]), ['buildings_map' => [], 'sites_map' => []]);

    // 2024-02-29 + 12 mois → 2025-03-01 (même débordement que Infocom::getWarrantyExpir()).
    expect($payload['warranty_end_date'])->toBe('2025-03-01');
});
