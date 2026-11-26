<?php

namespace App\Services\Glpi\Contracts;

/**
 * Handlers dont plusieurs items GLPI distincts et INDÉPENDANTS peuvent porter le même
 * nom, alors que Mercator exige un nom unique sur l'endpoint (ex. Database : deux
 * DatabaseInstance peuvent chacune héberger une base "prod" sans rapport entre elles,
 * cf. issue #17).
 *
 * Pour ces handlers, GlpiSyncService::sync() :
 *  - crée UN enregistrement Mercator par item GLPI (jamais de fusion des homonymes) ;
 *  - suffixe le nom de TOUS les homonymes d'un même groupe avec un libellé lisible
 *    (ex. "prod (srv-db01)"), choisi parmi homonymLabels(), puis l'id GLPI en dernier
 *    recours ("prod (#80)") — le résultat ne dépend pas de l'ordre de traitement ;
 *  - ne réconcilie par nom qu'avec des enregistrements Mercator NON tagués {GLPI}
 *    (un enregistrement tagué appartient à un item GLPI précis, ext_refs fait foi).
 */
interface DisambiguatesHomonymNames
{
    /**
     * Libellés candidats pour suffixer le nom d'un item homonyme, du plus lisible au
     * plus précis (ex. [nom de l'hôte, "hôte / instance"]). Les valeurs vides sont
     * ignorées ; le premier niveau qui distingue tous les homonymes du groupe est retenu.
     *
     * @return array<int, string|null>
     */
    public function homonymLabels(array $glpiItem, array $context): array;
}
