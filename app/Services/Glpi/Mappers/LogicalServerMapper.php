<?php

namespace App\Services\Glpi\Mappers;

use App\Services\Glpi\Mappers\Concerns\MapsInfocomInstallDate;

class LogicalServerMapper
{
    use MapsInfocomInstallDate;

    public function __construct(private readonly WorkstationMapper $base) {}

    /**
     * Mappe un Computer GLPI vers un payload Mercator logical_servers.
     * Réutilise la logique du WorkstationMapper — les champs sont identiques.
     */
    public function map(array $item, array $context): array
    {
        return $this->withInstallDate($this->base->map($item, $context), $item);
    }
}
