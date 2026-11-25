<?php

namespace App\Services\Glpi\Mappers\Concerns;

trait MapsInfocomInstallDate
{
    /**
     * Ajoute install_date (date de mise en service Infocom GLPI, `use_date`)
     * au payload des serveurs physiques et logiques Mercator (issue #19).
     *
     * Les postes de travail Mercator n'ont pas ce champ : ne pas l'ajouter dans
     * WorkstationMapper. Comme les autres dates Infocom, une date absente côté
     * GLPI n'est pas envoyée, pour ne pas écraser une saisie manuelle Mercator.
     */
    private function withInstallDate(array $payload, array $item): array
    {
        $date = $item['_infocoms']['use_date'] ?? null;

        if (is_string($date) && $date !== '' && ! str_starts_with($date, '0000-00-00')) {
            $payload['install_date'] = substr($date, 0, 10);
        }

        return $payload;
    }
}
