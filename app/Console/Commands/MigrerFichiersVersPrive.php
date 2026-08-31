<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Déplace les fichiers métier (photos d'équipements, documents) du disque
 * public (storage/app/public, servi sans authentification via /storage/...)
 * vers le disque privé (storage/app/private), d'où ils sont servis par
 * FichierController avec vérification organisation + module.
 *
 * À exécuter UNE FOIS lors du déploiement de la bascule vers le disque privé :
 *   php artisan fichiers:prive
 *
 * Idempotent : les fichiers déjà déplacés (absents du disque public) sont ignorés.
 * Le chemin relatif est conservé tel quel — les colonnes photo/chemin restent valides.
 */
class MigrerFichiersVersPrive extends Command
{
    protected $signature = 'fichiers:prive';

    protected $description = 'Déplace les fichiers métier du disque public vers le disque privé (storage/app/private)';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $prive = Storage::disk('local');

        $fichiers = $public->allFiles();
        $deplaces = 0;
        $echecs = [];

        foreach ($fichiers as $chemin) {
            if (str_ends_with($chemin, '.gitignore')) {
                continue;
            }

            $flux = $public->readStream($chemin);

            if ($prive->writeStream($chemin, $flux) === false) {
                $echecs[] = $chemin;
                continue;
            }

            if (is_resource($flux)) {
                fclose($flux);
            }

            $public->delete($chemin);
            $deplaces++;
        }

        $this->info("{$deplaces} fichier(s) déplacé(s) vers storage/app/private.");

        if ($echecs !== []) {
            $this->warn('Échecs (fichiers restés sur le disque public) :');
            foreach ($echecs as $chemin) {
                $this->warn("  - {$chemin}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
