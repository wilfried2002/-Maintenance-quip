<?php

namespace App\Services\Pdf;

/**
 * Générateur PDF minimaliste, sans dépendance (les rapports étaient limités à
 * un seul export avant la fonctionnalité 9/10). Génère du PDF 1.4 avec :
 * - texte Helvetica / Helvetica-Bold (polices standard, encodage Windows-1252
 *   pour les accents français) ;
 * - rectangles pleins/contour, traits (étiquettes, tableaux, QR) ;
 * - images JPEG (DCTDecode) pour la photo de l'équipement ;
 * - sauts de page automatiques pour les tableaux longs.
 *
 * Volontairement petit et auditable : ~250 lignes maîtrisées de bout en bout
 * plutôt qu'une dépendance lourde.
 */
class PdfDocument
{
    private const TAILLES = [
        'a4' => [595.28, 841.89],
        'a4l' => [841.89, 595.28],
        'a6l' => [420.94, 297.64],
    ];

    /** Largeurs AFM Helvetica (×1000), ASCII 32-126 ; défaut 556. */
    private const LARGEURS_HELVETICA = [
        32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556,
        64 => 1015, 65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556,
        96 => 333, 97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
        104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556,
        111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556,
        118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260,
        125 => 334, 126 => 584,
    ];

    /** Largeurs AFM Helvetica-Bold (×1000). */
    private const LARGEURS_HELVETICA_GRAS = [
        32 => 278, 33 => 333, 34 => 474, 35 => 556, 36 => 556, 37 => 889, 38 => 722, 39 => 238,
        40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
        48 => 556, 49 => 556, 50 => 556, 51 => 556, 52 => 556, 53 => 556, 54 => 556, 55 => 556,
        56 => 556, 57 => 556, 58 => 333, 59 => 333, 60 => 584, 61 => 584, 62 => 584, 63 => 611,
        64 => 975, 65 => 722, 66 => 722, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778,
        72 => 722, 73 => 278, 74 => 556, 75 => 722, 76 => 611, 77 => 833, 78 => 722, 79 => 778,
        80 => 667, 81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944,
        88 => 667, 89 => 667, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 584, 95 => 556,
        96 => 333, 97 => 556, 98 => 611, 99 => 556, 100 => 611, 101 => 556, 102 => 333, 103 => 611,
        104 => 611, 105 => 278, 106 => 278, 107 => 556, 108 => 278, 109 => 889, 110 => 611,
        111 => 611, 112 => 611, 113 => 611, 114 => 389, 115 => 556, 116 => 333, 117 => 611,
        118 => 556, 119 => 778, 120 => 556, 121 => 556, 122 => 500, 123 => 389, 124 => 280,
        125 => 389, 126 => 584,
    ];

    /** @var array<int, string> contenus d'objets PDF, index = numéro d'objet */
    private array $objets = [1 => '', 2 => ''];

    private int $prochainObjet = 3;

    /** @var array<string, int> numéros d'objets polices, par « famille » */
    private array $polices = [];

    /** @var array<string, array{id: int, largeur: int, hauteur: int}> */
    private array $images = [];

    /** @var array<int, array{contenu: string, objets: array<int>}> */
    private array $pages = [];

    private string $courant = '';

    private float $margeHaut;

    public float $y;

    public function __construct(
        private readonly string $format = 'a4',
        private readonly float $marge = 40.0,
    ) {
        $this->margeHaut = $this->marge;
        $this->y = 0.0;
    }

    public function largeur(): float
    {
        return self::TAILLES[$this->format][0];
    }

    public function hauteur(): float
    {
        return self::TAILLES[$this->format][1];
    }

    public function ajouterPage(): void
    {
        $this->pages[] = ['contenu' => '', 'objets' => []];
        $this->courant = '';
        $this->y = $this->hauteur() - $this->margeHaut;
    }

    private function ecrire(string $commande): void
    {
        $this->courant .= $commande."\n";
        $indice = array_key_last($this->pages);
        $this->pages[$indice]['contenu'] = $this->courant;
    }

    // ── Texte ───────────────────────────────────────────────────────────────

    public function texte(float $x, float $y, string $chaine, float $taille = 10, string $famille = 'helvetica', int $align = 0): void
    {
        $numPolice = $this->objetPolice($famille);
        $octets = $this->winansi($chaine);

        // Alignement : 1 = centré, 2 = à droite.
        if ($align !== 0) {
            $largeur = $this->largeurTexte($chaine, $taille, $famille);
            $x -= $align === 1 ? $largeur / 2 : $largeur;
        }

        $id = $this->enregistrerRessourcePage($numPolice, 'police');
        $this->ecrire(sprintf('BT /F%d %.2F Tf %.2F %.2F Td (%s) Tj ET', $id, $taille, $x, $y, $octets));
    }

    public function largeurTexte(string $chaine, float $taille, string $famille = 'helvetica'): float
    {
        $table = $famille === 'helvetica-gras' ? self::LARGEURS_HELVETICA_GRAS : self::LARGEURS_HELVETICA;
        $total = 0;

        foreach ($this->versWinansiOctets($chaine) as $octet) {
            $total += $table[$octet] ?? 556;
        }

        return $total * $taille / 1000;
    }

    /**
     * Tronque la chaîne pour qu'elle tienne dans la largeur donnée.
     */
    public function tronquer(string $chaine, float $taille, float $largeurMax, string $famille = 'helvetica'): string
    {
        if ($this->largeurTexte($chaine, $taille, $famille) <= $largeurMax) {
            return $chaine;
        }

        $tronquee = $chaine;

        while ($tronquee !== '' && $this->largeurTexte($tronquee.'…', $taille, $famille) > $largeurMax) {
            $tronquee = mb_substr($tronquee, 0, -1);
        }

        return $tronquee.'…';
    }

    // ── Formes ──────────────────────────────────────────────────────────────

    public function rectangle(float $x, float $y, float $largeur, float $hauteur, bool $plein = false): void
    {
        $this->ecrire(sprintf('%.2F %.2F %.2F %.2F re %s', $x, $y, $largeur, $hauteur, $plein ? 'f' : 'S'));
    }

    public function trait(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->ecrire(sprintf('%.2F %.2F m %.2F %.2F l S', $x1, $y1, $x2, $y2));
    }

    public function couleurTrait(int $r, int $v, int $b): void
    {
        $this->ecrire(sprintf('%.3F %.3F %.3F RG', $r / 255, $v / 255, $b / 255));
    }

    public function couleurRemplissage(int $r, int $v, int $b): void
    {
        $this->ecrire(sprintf('%.3F %.3F %.3F rg', $r / 255, $v / 255, $b / 255));
    }

    /**
     * Dessine une matrice de QR (lignes de 0/1, cf. QRCode::createMatrix)
     * en modules pleins — vectoriel, donc net à toute taille.
     */
    public function qr(array $matrice, float $x, float $y, float $taille): void
    {
        $n = count($matrice);
        $module = $taille / $n;
        $this->couleurRemplissage(0, 0, 0);

        foreach ($matrice as $ligne => $pixels) {
            foreach ($pixels as $colonne => $pixel) {
                if ($pixel) {
                    $this->rectangle($x + $colonne * $module, $y + $taille - ($ligne + 1) * $module, $module, $module, true);
                }
            }
        }
    }

    // ── Images JPEG ─────────────────────────────────────────────────────────

    /**
     * Insère un JPEG depuis un chemin absolu (DCTDecode : les octets du fichier
     * sont embarqués tels quels).
     */
    public function image(string $chemin, float $x, float $y, float $largeurMax, float $hauteurMax): bool
    {
        $donnees = @file_get_contents($chemin);

        if ($donnees === false || strlen($donnees) < 4 || substr($donnees, 0, 2) !== "\xFF\xD8") {
            return false; // pas un JPEG (PNG etc.) : ignoré proprement
        }

        $dimensions = $this->dimensionsJpeg($donnees);

        if ($dimensions === null) {
            return false;
        }

        $cle = md5($donnees);

        if (!isset($this->images[$cle])) {
            $largeurPx = $dimensions['largeur'];
            $hauteurPx = $dimensions['hauteur'];

            $num = $this->prochainObjet++;
            $this->objets[$num] = "<< /Type /XObject /Subtype /Image /Width {$largeurPx} /Height {$hauteurPx} "
                ."/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ".strlen($donnees)." >> stream\n"
                .$donnees."\nendstream";
$this->images[$cle] = ['id' => $num, 'largeur' => $largeurPx, 'hauteur' => $hauteurPx];
        }

        $image = $this->images[$cle];

        // Conserver les proportions dans le cadre donné.
        $echelle = min($largeurMax / $image['largeur'], $hauteurMax / $image['hauteur'], 1.0);
        $largeur = $image['largeur'] * $echelle;
        $hauteur = $image['hauteur'] * $echelle;

        $id = $this->enregistrerRessourcePage($image['id'], 'image');
        $this->ecrire(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
            $largeur, $hauteur, $x, $y, $id
        ));

        return true;
    }

    /**
     * Dimensions d'un JPEG : parcours des marqueurs jusqu'au premier SOF.
     *
     * @return array{largeur: int, hauteur: int}|null
     */
    private function dimensionsJpeg(string $donnees): ?array
    {
        $pos = 2;
        $longueurTotale = strlen($donnees);

        while ($pos + 9 < $longueurTotale) {
            if ($donnees[$pos] !== "\xFF") {
                $pos++;
                continue;
            }

            $marqueur = ord($donnees[$pos + 1]);

            if ($marqueur === 0xD8 || $marqueur === 0x01 || ($marqueur >= 0xD0 && $marqueur <= 0xD9)) {
                $pos += 2;
                continue;
            }

            $longueur = unpack('n', substr($donnees, $pos + 2, 2))[1];

            $estSof = $marqueur >= 0xC0 && $marqueur <= 0xCF
                && !in_array($marqueur, [0xC4, 0xC8, 0xCC], true);

            if ($estSof) {
                // SOF : précision (1 octet) puis hauteur puis largeur (big-endian).
                return [
                    'hauteur' => unpack('n', substr($donnees, $pos + 5, 2))[1],
                    'largeur' => unpack('n', substr($donnees, $pos + 7, 2))[1],
                ];
            }

            $pos += 2 + $longueur;
        }

        return null;
    }

    // ── Tableaux avec saut de page ──────────────────────────────────────────

    /**
     * En-tête + lignes ; retourne à la page suivante automatiquement.
     *
     * @param array<int, string> $entetes
     * @param array<float> $largeurs
     * @param array<array<int, string>> $lignes
     */
    public function tableau(array $entetes, array $largeurs, array $lignes, float $taille = 8.5): void
    {
        $hauteurLigne = 16.0;

        $dessinerEntetes = function () use ($entetes, $largeurs, $taille, $hauteurLigne): void {
            $x = $this->marge;
            $this->couleurRemplissage(230, 232, 240);
            $this->rectangle($this->marge, $this->y - $hauteurLigne, array_sum($largeurs), $hauteurLigne, true);
            $this->couleurRemplissage(0, 0, 0);

            foreach ($entetes as $i => $libelle) {
                $this->texte($x + 3, $this->y - 11.5, $libelle, $taille, 'helvetica-gras');
                $x += $largeurs[$i];
            }

            $this->y -= $hauteurLigne;
        };

        $this->sautePageSiNecessaire($hauteurLigne * 2);
        $dessinerEntetes();

        foreach ($lignes as $ligne) {
            if ($this->y - $hauteurLigne < $this->marge) {
                $this->ajouterPage();
                $dessinerEntetes();
            }

            $x = $this->marge;
            $maxCellules = 1;

            foreach ($ligne as $i => $valeur) {
                $valeur = (string) $valeur;
                $lignesTexte = $this->couper($valeur, $largeurs[$i] - 6, $taille);
                $maxCellules = max($maxCellules, count($lignesTexte));
            }

            $hauteur = $hauteurLigne * $maxCellules;

            if ($this->y - $hauteur < $this->marge) {
                $this->ajouterPage();
                $dessinerEntetes();
            }

            foreach ($ligne as $i => $valeur) {
                $lignesTexte = $this->couper((string) $valeur, $largeurs[$i] - 6, $taille);
                $decale = 0;

                foreach ($lignesTexte as $ligneTexte) {
                    $this->texte($x + 3, $this->y - 11.5 + $decale, $ligneTexte, $taille);
                    $decale -= $hauteurLigne;
                }

                $x += $largeurs[$i];
            }

            $this->y -= $hauteur;
            $this->couleurTrait(200, 202, 210);
            $this->trait($this->marge, $this->y, $this->largeur() - $this->marge, $this->y);
        }
    }

    public function sautePageSiNecessaire(float $hauteurRequise): bool
    {
        if ($this->y - $hauteurRequise < $this->marge) {
            $this->ajouterPage();

            return true;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function couper(string $texte, float $largeurMax, float $taille): array
    {
        $texte = trim($texte);

        if ($texte === '') {
            return ['—'];
        }

        $mots = preg_split('/\s+/', $texte) ?: [];
        $lignes = [];
        $courante = '';

        foreach ($mots as $mot) {
            $essai = $courante === '' ? $mot : $courante.' '.$mot;

            if ($this->largeurTexte($essai, $taille) <= $largeurMax || $courante === '') {
                $courante = $this->tronquer($essai, $taille, $largeurMax);
            } else {
                $lignes[] = $courante;
                $courante = $mot;
            }
        }

        $lignes[] = $courante;

        return array_slice($lignes, 0, 3); // 3 lignes max par cellule
    }

    // ── Assemblage ──────────────────────────────────────────────────────────

    public function sortie(): string
    {
        // Pages + contents.
        $numsPages = [];

        foreach ($this->pages as $i => $page) {
            $numPage = $this->prochainObjet++;
            $numContenu = $this->prochainObjet++;
            $numsPages[] = $numPage;

            $this->objets[$numPage] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font %s /XObject %s >> /Contents %d 0 R >>",
                $this->largeur(),
                $this->hauteur(),
                $this->dictionnaireRessources($page, 'F', 'police'),
                $this->dictionnaireRessources($page, 'I', 'image'),
                $numContenu
            );

            $contenu = $page['contenu'];
            $this->objets[$numContenu] = "<< /Length ".strlen($contenu)." >>\nstream\n".$contenu."\nendstream";
        }

        $this->objets[2] = '<< /Type /Pages /Kids ['.implode(' ', $numsPages).'] /Count '.count($numsPages).' >>';
        $this->objets[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $decalages = [];

        foreach ($this->objets as $num => $contenu) {
            $decalages[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$contenu}\nendobj\n";
        }

        $xref = strlen($pdf);
        $total = count($this->objets) + 1;
        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";

        for ($num = 1; $num <= count($this->objets); $num++) {
            $pdf .= sprintf("%010d 00000 n \n", $decalages[$num]);
        }

        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    // ── Internes ────────────────────────────────────────────────────────────

    private function objetPolice(string $famille): int
    {
        if (!isset($this->polices[$famille])) {
            $base = $famille === 'helvetica-gras' ? '/Helvetica-Bold' : '/Helvetica';
            $num = $this->prochainObjet++;
            $this->objets[$num] = "<< /Type /Font /Subtype /Type1 {$base} /Encoding /WinAnsiEncoding >>";
            $this->polices[$famille] = $num;
        }

        return $this->polices[$famille];
    }

    /**
     * Associe une ressource à la page courante et renvoie son identifiant local.
     */
    private function enregistrerRessourcePage(int $numObjet, string $type): int
    {
        $indice = array_key_last($this->pages);
        $cle = $type.'_'.$numObjet;

        if (!isset($this->pages[$indice]['objets'][$cle])) {
            $suivant = count($this->pages[$indice]['objets']) + 1;
            $this->pages[$indice]['objets'][$cle] = $suivant;
        }

        return $this->pages[$indice]['objets'][$cle];
    }

    private function dictionnaireRessources(array $page, string $prefixe, string $type): string
    {
        $entrees = [];

        foreach ($page['objets'] as $cle => $id) {
            if (str_starts_with($cle, $type.'_')) {
                $entrees[] = "/{$prefixe}{$id} ".explode('_', $cle)[1].' 0 R';
            }
        }

        return $entrees === [] ? '<< >>' : '<< '.implode(' ', $entrees).' >>';
    }

    /**
     * UTF-8 → Windows-1252 échappé pour l'opérateur Tj.
     */
    private function winansi(string $chaine): string
    {
        $sortie = '';

        foreach ($this->versWinansiOctets($chaine) as $octet) {
            $car = chr($octet);

            if ($car === '(' || $car === ')' || $car === '\\') {
                $sortie .= '\\'.$car;
            } else {
                $sortie .= $car;
            }
        }

        return $sortie;
    }

    /**
     * @return array<int, int>
     */
    private function versWinansiOctets(string $chaine): array
    {
        $octets = [];

        foreach (mb_str_split($chaine) ?: [] as $caractere) {
            $octets[] = match ($caractere) {
                '€' => 128,
                '…' => 133,
                '‘', '’' => 146,
                '“', '”' => 147,
                '–' => 150,
                '—' => 151,
                default => $this->latinVersOctet($caractere),
            };
        }

        return $octets;
    }

    private function latinVersOctet(string $caractere): int
    {
        $unicode = mb_ord($caractere);

        if ($unicode !== false && $unicode >= 32 && $unicode <= 126) {
            return $unicode;
        }

        // Latin-1 étendu (é, è, à, ç…) : identique en cp1252 entre 160 et 255.
        if ($unicode !== false && $unicode >= 160 && $unicode <= 255) {
            return $unicode;
        }

        return ord('?');
    }
}
