<?php

namespace App\Services\Glpi\Mappers;

use App\Services\Glpi\Mappers\Concerns\MapsInfocomInstallDate;
use App\Services\Glpi\Mappers\Concerns\ResolvesGlpiBay;

class PhysicalServerMapper
{
    use MapsInfocomInstallDate;
    use ResolvesGlpiBay;

    public function __construct(private readonly WorkstationMapper $base) {}

    /**
     * Mappe un Computer GLPI vers un payload Mercator physical_servers.
     * Réutilise la logique du WorkstationMapper et ajoute bay_id si le serveur
     * est racké (glpi_items_racks.itemtype = "Computer").
     */
    public function map(array $item, array $context): array
    {
        $payload = $this->withInstallDate($this->base->map($item, $context), $item);

        $bayId = $this->resolveBayId('Computer', $item['id'], $context);
        if ($bayId !== null) {
            $payload['bay_id'] = $bayId;
        }

        return $payload;
    }
}
