<?php

namespace App\Services\Glpi\Handlers;

use App\Services\Glpi\Contracts\DisambiguatesHomonymNames;
use App\Services\Glpi\Contracts\SupportsDatabaseInstanceResolution;
use App\Services\Glpi\Contracts\SyncHandler;
use App\Services\Glpi\Mappers\DatabaseMapper;
use Illuminate\Support\Facades\Log;

class DatabaseSyncHandler implements DisambiguatesHomonymNames, SupportsDatabaseInstanceResolution, SyncHandler
{
    public function __construct(private readonly DatabaseMapper $mapper) {}

    public function glpiItemType(): string
    {
        return 'Database';
    }

    public function mercatorEndpoint(): string
    {
        return 'databases';
    }

    public function glpiQueryParams(): array
    {
        return [
            'range' => '0-999',
            'expand_dropdowns' => 1,
            'with_items' => 1,
        ];
    }

    public function processOrphans(): bool
    {
        // Supprime (ou marque [OLD]) les items Mercator absents de GLPI, cf. issue #13.
        return true;
    }

    public function filterItem(array $item): bool
    {
        // Opt-in : ne conserver que les bases actives (glpi_databases.is_active = 1), cf. issue #17.
        // Comportement historique (flag absent/false) : toutes les bases sont conservées.
        if (! config('glpi.sync.only_active_databases', false)) {
            return true;
        }

        // is_active reste un booléen 0/1 même avec expand_dropdowns=1 (ce n'est pas un dropdown).
        // Champ absent (API qui ne le renvoie pas) : on conserve pour éviter une exclusion
        // silencieuse, avec un avertissement journalisé (la colonne est NOT NULL côté GLPI).
        if (! array_key_exists('is_active', $item)) {
            Log::warning('[databases] GLPI_SYNC_ONLY_ACTIVE_DATABASES=true mais champ is_active absent — base "'.($item['name'] ?? '?').'" conservée');

            return true;
        }

        return (int) $item['is_active'] === 1;
    }

    public function map(array $glpiItem, array $context): array
    {
        return $this->mapper->map($glpiItem, $context);
    }

    /**
     * Deux bases homonymes sont distinguées par leur hôte (serveur de la DatabaseInstance),
     * puis par "hôte / instance" si elles partagent le même hôte (ex. une base "glpi" sur
     * une instance MariaDB et une autre sur une instance PostgreSQL du même serveur).
     * Sans hôte résolu, le nom de l'instance seul sert de libellé.
     * cf. GlpiSyncService::buildDatabaseInstanceMaps() / resolveDatabaseHostNames().
     */
    public function homonymLabels(array $glpiItem, array $context): array
    {
        $id = (int) ($glpiItem['id'] ?? 0);
        $host = trim((string) ($context['database_hosts'][$id] ?? ''));
        $instance = trim((string) ($context['database_instances'][$id]['name'] ?? ''));

        if ($host === '') {
            return [$instance];
        }

        return [$host, $instance !== '' ? "{$host} / {$instance}" : null];
    }
}
