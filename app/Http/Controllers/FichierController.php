<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Vehicule;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servit les fichiers (photos d'équipements, documents rattachés) depuis le
 * disque PRIVÉ (storage/app/private, voir config/filesystems.php : disque
 * « local ») : plus aucun fichier métier ne vit sous public/storage, où il
 * était accessible sans authentification à quiconque connaissait l'URL.
 *
 * Chaque téléchargement passe par :
 *   1. le cloisonnement organisation (scope global BelongsToOrganisation lors
 *      du binding de route / findOrFail) ;
 *   2. la permission de l'utilisateur sur le module de l'équipement concerné
 *      (même sémantique que les pages du module : rôles par défaut + overrides).
 */
class FichierController extends Controller
{
    private const CLASSES_PAR_TYPE = [
        'industriels' => EquipementIndustriel::class,
        'vehicules' => Vehicule::class,
        'bureau' => EquipementBureau::class,
    ];

    /**
     * Document rattaché à un équipement (facture, notice, photo…).
     */
    public function document(Request $request, Document $document): Response
    {
        $module = RoleService::modulePourClasseEquipement($document->equipementable_type);

        if ($module === null) {
            abort(404);
        }

        abort_unless(
            $request->user()->hasModuleAccess($module),
            403,
            'Vous n\'avez pas accès au module de l\'équipement concerné par ce document.'
        );

        return $this->streamer($document->chemin, $document->nom_original);
    }

    /**
     * Photo d'un équipement. Le type court dans l'URL (« industriels »,
     * « vehicules », « bureau ») est fourni par HasPhoto::getPhotoUrlAttribute.
     */
    public function photo(Request $request, string $type, int $id): Response
    {
        $classe = self::CLASSES_PAR_TYPE[$type] ?? null;

        if ($classe === null) {
            abort(404);
        }

        // findOrFail passe par le scope organisation : la photo d'un équipement
        // d'une autre organisation est introuvable (404), jamais servie.
        $equipement = $classe::findOrFail($id);

        abort_unless(
            $request->user()->hasModuleAccess(RoleService::modulePourClasseEquipement($classe)),
            403,
            'Vous n\'avez pas accès au module de cet équipement.'
        );

        abort_if(!$equipement->photo, 404);

        return $this->streamer($equipement->photo);
    }

    private function streamer(string $chemin, ?string $nomOriginal = null): Response
    {
        abort_unless(Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response($chemin, $nomOriginal ?? basename($chemin));
    }
}
