<?php

use App\Services\Glpi\Contracts\GlpiClientInterface;
use App\Services\Glpi\GlpiSyncService;
use App\Services\Glpi\Handlers\DatabaseSyncHandler;
use App\Services\Glpi\Mappers\DatabaseMapper;
use App\Services\Mercator\Contracts\MercatorClientInterface;
use Mockery\MockInterface;

// ── Bases homonymes (cf. issue #17) ──────────────────────────────────────────
// Deux Database GLPI de même nom sur des DatabaseInstance différentes sont des bases
// INDÉPENDANTES : chacune son enregistrement Mercator. Mercator exigeant un nom unique,
// tous les homonymes sont suffixés par leur hôte (puis "hôte / instance", puis l'id GLPI).

/**
 * @param  array  $databases  Database GLPI (databaseinstances_id numérique)
 * @param  array  $instances  id → ['name' => …, 'itemtype' => …, 'items_id' => …]
 * @param  array  $hosts  "Itemtype#id" → nom de l'hôte
 */
function homonymGlpiMock(array $databases, array $instances = [], array $hosts = []): MockInterface
{
    $mock = Mockery::mock(GlpiClientInterface::class);
    $mock->shouldReceive('getItems')->with('Database', Mockery::any())->andReturn($databases);
    $mock->shouldReceive('getItems')->with('DatabaseInstance', Mockery::any())->andReturn(
        array_map(fn ($id, $i) => ['id' => $id, 'name' => $i['name'] ?? null, 'databaseinstancetypes_id' => ''], array_keys($instances), $instances)
    );
    $mock->shouldReceive('getItem')->with('DatabaseInstance', Mockery::any(), Mockery::any())
        ->andReturnUsing(fn ($t, $id) => ['id' => $id] + ($instances[$id] ?? []));
    $mock->shouldReceive('getItem')->with(Mockery::any(), Mockery::any())
        ->andReturnUsing(fn ($t, $id) => isset($hosts["{$t}#{$id}"]) ? ['id' => $id, 'name' => $hosts["{$t}#{$id}"]] : []);
    $mock->shouldReceive('getSubItems')->andReturn([]);

    return $mock;
}

/**
 * Mercator simulé : enregistre créations/mises à jour/suppressions.
 */
function homonymMercatorMock(array $databases, ?array &$log): MockInterface
{
    $log = ['create' => [], 'update' => [], 'delete' => []];
    $mock = Mockery::mock(MercatorClientInterface::class);
    $mock->shouldReceive('getBuildings')->andReturn([]);
    $mock->shouldReceive('getSites')->andReturn([]);
    $mock->shouldReceive('getAll')->andReturn($databases);
    $mock->shouldReceive('create')->andReturnUsing(function (string $ep, array $payload) use (&$log) {
        $log['create'][] = $payload;

        return ['id' => 900 + count($log['create'])];
    });
    $mock->shouldReceive('update')->andReturnUsing(function (string $ep, int $id, array $payload) use (&$log) {
        $log['update'][] = compact('id', 'payload');

        return [];
    });
    $mock->shouldReceive('delete')->andReturnUsing(function (string $ep, int $id) use (&$log) {
        $log['delete'][] = $id;
    });

    return $mock;
}

function syncDatabases(MockInterface $glpi, MockInterface $mercator): array
{
    return (new GlpiSyncService)->sync($glpi, $mercator, new DatabaseSyncHandler(new DatabaseMapper));
}

$twoHosts = [
    'databases' => [
        ['id' => 4, 'name' => 'prod', 'databaseinstances_id' => 40],
        ['id' => 80, 'name' => 'prod', 'databaseinstances_id' => 800],
    ],
    'instances' => [
        40 => ['name' => 'MariaDB', 'itemtype' => 'Computer', 'items_id' => 55],
        800 => ['name' => 'MariaDB', 'itemtype' => 'Computer', 'items_id' => 56],
    ],
    'hosts' => ['Computer#55' => 'srv-a', 'Computer#56' => 'srv-b'],
];

// ── Tests ─────────────────────────────────────────────────────────────────────

it('crée un enregistrement Mercator par Database GLPI homonyme, suffixé par son hôte', function () use ($twoHosts) {
    $mercator = homonymMercatorMock([], $log);

    $stats = syncDatabases(homonymGlpiMock(...$twoHosts), $mercator);

    expect($stats['created'])->toBe(2)
        ->and($stats['errors'])->toBe(0)
        ->and(array_column($log['create'], 'name'))->toBe(['prod (srv-a)', 'prod (srv-b)'])
        ->and(array_column($log['create'], 'ext_refs'))->toBe(['{GLPI}4', '{GLPI}80']);
});

it('produit les mêmes noms quel que soit l\'ordre des items GLPI', function () use ($twoHosts) {
    $mercator = homonymMercatorMock([], $log);
    $reversed = ['databases' => array_reverse($twoHosts['databases'])] + $twoHosts;

    syncDatabases(homonymGlpiMock(...$reversed), $mercator);

    expect(array_column($log['create'], 'name'))->toBe(['prod (srv-b)', 'prod (srv-a)']);
});

it('distingue deux homonymes du même hôte par "hôte / instance"', function () {
    $mercator = homonymMercatorMock([], $log);

    syncDatabases(homonymGlpiMock(
        databases: [
            ['id' => 1, 'name' => 'glpi', 'databaseinstances_id' => 10],
            ['id' => 2, 'name' => 'glpi', 'databaseinstances_id' => 20],
        ],
        instances: [
            10 => ['name' => 'MariaDB', 'itemtype' => 'Computer', 'items_id' => 55],
            20 => ['name' => 'PostgreSQL', 'itemtype' => 'Computer', 'items_id' => 55],
        ],
        hosts: ['Computer#55' => 'srv-a'],
    ), $mercator);

    expect(array_column($log['create'], 'name'))->toBe(['glpi (srv-a / MariaDB)', 'glpi (srv-a / PostgreSQL)']);
});

it('se rabat sur le nom de l\'instance sans hôte, puis sur l\'id GLPI', function () {
    $mercator = homonymMercatorMock([], $log);

    syncDatabases(homonymGlpiMock(
        databases: [
            ['id' => 1, 'name' => 'app', 'databaseinstances_id' => 10],
            ['id' => 2, 'name' => 'app', 'databaseinstances_id' => 0],
        ],
        instances: [10 => ['name' => 'Oracle-PRD', 'itemtype' => null, 'items_id' => 0]],
    ), $mercator);

    expect(array_column($log['create'], 'name'))->toBe(['app (Oracle-PRD)', 'app (#2)']);
});

it('sépare un enregistrement fusionné par une version précédente ({GLPI}4|{GLPI}80)', function () use ($twoHosts) {
    $mercator = homonymMercatorMock([
        ['id' => 500, 'name' => 'prod', 'ext_refs' => '{GLPI}4|{GLPI}80'],
    ], $log);

    $stats = syncDatabases(homonymGlpiMock(...$twoHosts), $mercator);

    expect($stats['errors'])->toBe(0)
        ->and($stats['deleted'])->toBe(0)
        ->and($log['update'])->toHaveCount(1)
        ->and($log['update'][0]['id'])->toBe(500)
        ->and($log['update'][0]['payload']['name'])->toBe('prod (srv-a)')
        ->and($log['update'][0]['payload']['ext_refs'])->toBe('{GLPI}4')
        ->and($log['create'])->toHaveCount(1)
        ->and($log['create'][0]['name'])->toBe('prod (srv-b)')
        ->and($log['create'][0]['ext_refs'])->toBe('{GLPI}80');
});

it('renomme un ancien suffixe d\'id ("prod (80)") en suffixe d\'hôte, via ext_refs', function () use ($twoHosts) {
    $mercator = homonymMercatorMock([
        ['id' => 500, 'name' => 'prod', 'ext_refs' => '{GLPI}4'],
        ['id' => 501, 'name' => 'prod (80)', 'ext_refs' => '{GLPI}80'],
    ], $log);

    $stats = syncDatabases(homonymGlpiMock(...$twoHosts), $mercator);

    expect($stats['created'])->toBe(0)
        ->and($stats['updated'])->toBe(2)
        ->and(array_column(array_column($log['update'], 'payload'), 'name', null))->toBe(['prod (srv-a)', 'prod (srv-b)'])
        ->and(array_column($log['update'], 'id'))->toBe([500, 501]);
});

it('réconcilie une Database non homonyme sur un enregistrement Mercator non tagué de même nom', function () {
    $mercator = homonymMercatorMock([
        ['id' => 500, 'name' => 'prod', 'ext_refs' => null],
    ], $log);

    $stats = syncDatabases(homonymGlpiMock([['id' => 1, 'name' => 'prod', 'databaseinstances_id' => 10]]), $mercator);

    expect($stats['created'])->toBe(0)
        ->and($stats['marked_old'])->toBe(0)
        ->and($log['update'][0]['id'])->toBe(500)
        ->and($log['update'][0]['payload']['name'])->toBe('prod')
        ->and($log['update'][0]['payload']['ext_refs'])->toBe('{GLPI}1');
});

it('ne s\'approprie pas par son nom l\'enregistrement tagué d\'une autre Database GLPI', function () {
    // "prod" {GLPI}4 appartient à une base absente du lot (supprimée, inactive…) : la
    // base GLPI 99, également nommée "prod", est créée à part — suffixée de son id tant
    // que le nom reste pris (l'orphelin est supprimé en fin de run, le suffixe
    // disparaît au run suivant).
    $mercator = homonymMercatorMock([
        ['id' => 500, 'name' => 'prod', 'ext_refs' => '{GLPI}4'],
    ], $log);

    $stats = syncDatabases(homonymGlpiMock([['id' => 99, 'name' => 'prod', 'databaseinstances_id' => 10]]), $mercator);

    expect($stats['errors'])->toBe(0)
        ->and($log['create'][0]['name'])->toBe('prod (#99)')
        ->and($log['create'][0]['ext_refs'])->toBe('{GLPI}99')
        ->and($log['delete'])->toBe([500]);
});

it('tronque le nom de base pour respecter les 64 caractères de Mercator', function () {
    $long = str_repeat('x', 70);
    $mercator = homonymMercatorMock([], $log);

    syncDatabases(homonymGlpiMock(
        databases: [
            ['id' => 1, 'name' => $long, 'databaseinstances_id' => 10],
            ['id' => 2, 'name' => $long, 'databaseinstances_id' => 20],
        ],
        instances: [
            10 => ['name' => 'i1', 'itemtype' => 'Computer', 'items_id' => 55],
            20 => ['name' => 'i2', 'itemtype' => 'Computer', 'items_id' => 56],
        ],
        hosts: ['Computer#55' => 'srv-a', 'Computer#56' => 'srv-b'],
    ), $mercator);

    foreach ($log['create'] as $payload) {
        expect(mb_strlen($payload['name']))->toBeLessThanOrEqual(64);
    }
    expect($log['create'][0]['name'])->toEndWith(' (srv-a)');
});
