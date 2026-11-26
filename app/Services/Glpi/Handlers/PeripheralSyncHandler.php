<?php

namespace App\Services\Glpi\Handlers;

use App\Services\Glpi\Contracts\SupportsGlpiItemDetail;
use App\Services\Glpi\Contracts\SyncHandler;
use App\Services\Glpi\Mappers\PeripheralMapper;

class PeripheralSyncHandler implements SupportsGlpiItemDetail, SyncHandler
{
    public function __construct(private readonly PeripheralMapper $mapper) {}

    public function glpiItemType(): string
    {
        return 'Peripheral';
    }

    public function mercatorEndpoint(): string
    {
        return 'peripherals';
    }

    public function glpiQueryParams(): array
    {
        return [
            'range'             => '0-999',
            'expand_dropdowns'  => 1,
            'with_networkports' => 1,
        ];
    }

    /**
     * Infocom (mise en service, garantie, réforme — issue #19) : with_infocoms n'est
     * pas fiable sur la requête de collection, il est demandé item par item.
     * expand_dropdowns=1 pour que la fusion avec l'item de collection conserve les
     * libellés (fabricant, modèle, lieu…) et non des ids.
     */
    public function glpiDetailParams(): array
    {
        return [
            'expand_dropdowns' => 1,
            'with_infocoms' => 1,
        ];
    }

    public function processOrphans(): bool
    {
        // Supprime (ou marque [OLD]) les items Mercator absents de GLPI, cf. issue #13.
        return true;
    }

    public function filterItem(array $item): bool
    {
        return true;
    }

    public function map(array $glpiItem, array $context): array
    {
        return $this->mapper->map($glpiItem, $context);
    }
}
