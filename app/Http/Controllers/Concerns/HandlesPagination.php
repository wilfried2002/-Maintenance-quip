<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Paramètres de pagination/tri serveur partagés par les listes paginées
 * (équipements, interventions, plans, pièces, coûts). Le tri est restreint à une
 * liste blanche de colonnes réelles : jamais de colonne issue de la requête
 * directement dans orderBy().
 */
trait HandlesPagination
{
    /**
     * @return array{0: string, 1: string, 2: int} [colonne de tri, sens, par page]
     */
    protected function parametresTri(Request $request, array $colonnesAutorisees, string $triDefaut, string $sensDefaut = 'desc'): array
    {
        $tri = (string) $request->query('sort', $triDefaut);

        if (!in_array($tri, $colonnesAutorisees, true)) {
            $tri = $triDefaut;
        }

        $sens = match ($request->query('dir')) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => $sensDefaut,
        };

        $parPage = min(100, max(5, (int) $request->query('per_page', 15)));

        return [$tri, $sens, $parPage];
    }

    protected function termeRecherche(Request $request): string
    {
        return trim((string) $request->query('q', ''));
    }
}
