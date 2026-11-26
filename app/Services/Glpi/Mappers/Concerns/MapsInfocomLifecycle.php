<?php

namespace App\Services\Glpi\Mappers\Concerns;

/**
 * Cycle de vie d'un actif à partir de l'onglet Infocom GLPI (issue #19) :
 *
 *  - install_date      ← use_date          (date de mise en service)
 *  - warranty_end_date ← warranty_date + warranty_duration mois
 *  - end_of_life_date  ← decommission_date (date de réforme)
 *
 * Infocom n'expose pas de date de fin de garantie : GLPI la calcule à l'affichage
 * (Infocom::getWarrantyExpir() : strtotime("<warranty_date> +<durée> month")).
 * Une durée de -1 signifie « garantie à vie » : pas de date de fin.
 *
 * Comme pour les autres champs, une date absente côté GLPI n'est pas envoyée,
 * pour ne pas écraser une saisie manuelle dans Mercator.
 *
 * Nécessite que l'item ait été récupéré avec with_infocoms=1 (cf.
 * SupportsGlpiItemDetail::glpiDetailParams()).
 */
trait MapsInfocomLifecycle
{
    /**
     * @return array<string, string>
     */
    private function infocomLifecycle(array $item): array
    {
        $infocom = $item['_infocoms'] ?? [];

        return array_filter([
            'install_date' => $this->infocomDate($infocom['use_date'] ?? null),
            'warranty_end_date' => $this->infocomWarrantyEnd($infocom),
            'end_of_life_date' => $this->infocomDate($infocom['decommission_date'] ?? null),
        ], fn ($v) => $v !== null);
    }

    /**
     * Normalise une date Infocom (« Y-m-d » ou « Y-m-d H:i:s ») en « Y-m-d ».
     * Les dates vides ou « 0000-00-00 » renvoient null.
     */
    private function infocomDate(mixed $date): ?string
    {
        if (! is_string($date) || $date === '' || str_starts_with($date, '0000-00-00')) {
            return null;
        }

        return substr($date, 0, 10);
    }

    private function infocomWarrantyEnd(array $infocom): ?string
    {
        $start = $this->infocomDate($infocom['warranty_date'] ?? null);
        $months = (int) ($infocom['warranty_duration'] ?? 0);

        if ($start === null || $months <= 0) {
            return null;
        }

        $timestamp = strtotime("{$start} +{$months} month");

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }
}
