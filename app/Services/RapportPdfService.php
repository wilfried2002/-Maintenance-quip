<?php

namespace App\Services;

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\PlanMaintenance;
use App\Models\Vehicule;
use App\Services\Pdf\PdfDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Construit les PDF de la fonctionnalité 9/10 : fiche équipement, listes
 * (équipements / interventions / pièces) et étiquette avec QR code pointant
 * vers la fiche. Repose sur le moteur maison PdfDocument et le générateur QR
 * vendorisé (app/Libraries/QRCode.php).
 */
class RapportPdfService
{
    public function fiche(object $equipement, string $titreModule): string
    {
        $pdf = new PdfDocument('a4');
        $pdf->ajouterPage();

        $this->entete($pdf, 'Fiche équipement — '.$titreModule);

        // Photo (disque privé) en haut à droite si présente.
        if (!empty($equipement->photo)) {
            $chemin = Storage::disk('private')->path($equipement->photo);
            $pdf->image($chemin, 435, $pdf->y - 150, 120, 150);
        }

        $pdf->texte($pdf->marge, $pdf->y - 10, (string) ($equipement->immatriculation ?? $equipement->designation ?? $equipement->code), 20, 'helvetica-gras');
        $pdf->y -= 34;

        // Attributs pertinents, libellés lisibles.
        $attributs = array_filter([
            'Code' => $equipement->code ?? null,
            'Marque' => $equipement->marque ?? null,
            'Catégorie' => $equipement->categorie ?? null,
            'Statut' => $equipement->statut ?? null,
            'Criticité' => $equipement->criticite ?? null,
            'Garantie jusqu\'au' => isset($equipement->date_fin_garantie) ? (string) $equipement->date_fin_garantie : null,
            'Kilométrage' => isset($equipement->kilometrage_actuel) ? number_format((float) $equipement->kilometrage_actuel, 0, ',', ' ').' km' : null,
            'Localisation' => $equipement->localisation ?? null,
        ], fn ($valeur) => $valeur !== null && $valeur !== '');

        $x = $pdf->marge;

        foreach ($attributs as $libelle => $valeur) {
            $pdf->texte($x, $pdf->y, $libelle, 8, 'helvetica-gras');
            $pdf->texte($x, $pdf->y - 12, (string) $valeur, 10);
            $x += 120;

            if ($x > 420) {
                $x = $pdf->marge;
                $pdf->y -= 30;
            }
        }

        if ($x !== $pdf->marge) {
            $pdf->y -= 30;
        }

        // Interventions récentes.
        $pdf->y -= 16;
        $this->titre($pdf, 'Interventions récentes');

        $interventions = Intervention::query()
            ->where('equipementable_type', $equipement::class)
            ->where('equipementable_id', $equipement->id)
            ->orderByDesc('date_planifiee')
            ->limit(10)
            ->get();

        $pdf->tableau(
            ['Date', 'Titre', 'Type', 'Statut', 'Priorité'],
            [70, 220, 65, 75, 65],
            $interventions->map(fn (Intervention $i) => [
                optional($i->date_planifiee)->format('d/m/Y') ?? '—',
                $i->titre,
                $i->type_intervention,
                $i->statut,
                $i->priorite,
            ])->all()
        );

        // Plans préventifs.
        $pdf->y -= 16;
        $this->titre($pdf, 'Plans de maintenance préventive');

        $plans = PlanMaintenance::query()
            ->where('equipementable_type', $equipement::class)
            ->where('equipementable_id', $equipement->id)
            ->orderBy('operation')
            ->limit(10)
            ->get();

        $pdf->tableau(
            ['Opération', 'Fréquence', 'Dernière exécution', 'Actif'],
            [220, 120, 120, 60],
            $plans->map(fn (PlanMaintenance $p) => [
                $p->operation,
                $p->frequence_valeur.' '.$p->frequence_unite,
                optional($p->derniere_execution_date)->format('d/m/Y') ?? '—',
                $p->actif ? 'Oui' : 'Non',
            ])->all()
        );

        return $pdf->sortie();
    }

    /**
     * @param array<int, string> $entetes
     * @param array<float> $largeurs
     * @param array<array<int, string>> $lignes
     */
    public function liste(string $titre, array $entetes, array $largeurs, array $lignes): string
    {
        $pdf = new PdfDocument('a4l');
        $pdf->ajouterPage();

        $this->entete($pdf, $titre);
        $pdf->y -= 8;
        $pdf->tableau($entetes, $largeurs, $lignes);

        return $pdf->sortie();
    }

    /**
     * Étiquette A6 paysage : code en gros, désignation, QR vers la fiche.
     */
    public function etiquette(object $equipement, string $titreModule, string $url): string
    {
        $pdf = new PdfDocument('a6l');
        $pdf->ajouterPage();

        $pdf->couleurRemplissage(30, 41, 59);
        $pdf->rectangle(0, 268, $pdf->largeur(), 30, true);
        $pdf->couleurRemplissage(255, 255, 255);
        $pdf->texte(12, 276, config('app.name').' — '.$titreModule, 8, 'helvetica-gras');
        $pdf->texte($pdf->largeur() - 12, 276, now()->format('d/m/Y'), 7, 'helvetica', 2);

        $pdf->couleurRemplissage(0, 0, 0);
        $pdf->texte(12, 228, (string) $equipement->code, 26, 'helvetica-gras');
        $pdf->texte(12, 206, (string) ($equipement->immatriculation ?? $equipement->designation ?? ''), 13, 'helvetica-gras');
        $pdf->texte(12, 190, $this->libelleEquipement($equipement), 9);

        $qr = new \App\Libraries\QRCode($url, ['s' => 'qrm']);
        $pdf->qr($qr->createMatrix(), 268, 40, 205);
        $pdf->texte(370, 32, 'Scanner pour ouvrir la fiche', 6.5);

        return $pdf->sortie();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function entete(PdfDocument $pdf, string $titre): void
    {
        $pdf->couleurRemplissage(30, 41, 59);
        $pdf->rectangle(0, $pdf->hauteur() - 24, $pdf->largeur(), 24, true);
        $pdf->couleurRemplissage(255, 255, 255);
        $pdf->texte($pdf->marge, $pdf->hauteur() - 16, $titre, 11, 'helvetica-gras');
        $pdf->texte($pdf->largeur() - $pdf->marge, $pdf->hauteur() - 16, now()->format('d/m/Y H:i'), 8, 'helvetica', 2);
        $pdf->couleurRemplissage(0, 0, 0);
        $pdf->y = $pdf->hauteur() - 40;
    }

    private function titre(PdfDocument $pdf, string $titre): void
    {
        $pdf->sautePageSiNecessaire(30);
        $pdf->texte($pdf->marge, $pdf->y, $titre, 12, 'helvetica-gras');
        $pdf->y -= 10;
    }

    private function libelleEquipement(object $equipement): string
    {
        $parties = array_filter([
            $equipement->marque ?? null,
            $equipement->modele ?? null,
            $equipement->localisation ?? null,
        ]);

        return implode(' · ', $parties !== [] ? $parties : ['—']);
    }
}
