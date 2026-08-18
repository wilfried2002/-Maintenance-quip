<?php

use App\Http\Controllers\CoutEntretienController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipementBureauController;
use App\Http\Controllers\EquipementIndustrielController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\IndicateurController;
use App\Http\Controllers\InterventionController;
use App\Http\Controllers\InterventionRapportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\OrganisationSwitchController;
use App\Http\Controllers\PieceConsumptionController;
use App\Http\Controllers\PieceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::middleware(['auth', 'verified', 'check.organisation'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Recherche globale (barre du topbar) : pas de check.role, le filtrage par module se
    // fait à l'intérieur de SearchService (même principe que la sidebar : accessibleModules).
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');

    // Stock de pièces propre à ce module : groupe séparé (et non imbriqué dans celui
    // ci-dessous) car sa liste de rôles autorisés diffère — magasinier y a accès sans
    // avoir accès au reste du module équipement. Voir HandlesPieces et Piece::module.
    // Enregistré AVANT le groupe suivant : sinon sa route show '/{equipementIndustriel}'
    // (wildcard) intercepterait '/equipements-industriels/pieces' en premier.
    Route::middleware('check.role:responsable_maintenance,technicien,magasinier')
        ->prefix('equipements-industriels/pieces')
        ->name('equipements-industriels.pieces.')
        ->group(function () {
            Route::get('/', [EquipementIndustrielController::class, 'piecesIndex'])->name('index');
            Route::post('/', [EquipementIndustrielController::class, 'piecesStore'])->name('store');
            Route::put('/{piece}', [EquipementIndustrielController::class, 'piecesUpdate'])->name('update');
            Route::delete('/{piece}', [EquipementIndustrielController::class, 'piecesDestroy'])->name('destroy');
        });

    Route::middleware('check.role:responsable_maintenance,technicien,superviseur,user')
        ->prefix('equipements-industriels')
        ->name('equipements-industriels.')
        ->group(function () {
            Route::get('/', [EquipementIndustrielController::class, 'index'])->name('index');
            Route::post('/', [EquipementIndustrielController::class, 'store'])->name('store');

            Route::get('/dashboard', [EquipementIndustrielController::class, 'dashboard'])->name('dashboard');

            Route::get('/interventions', [EquipementIndustrielController::class, 'interventionsIndex'])->name('interventions.index');
            Route::post('/interventions', [EquipementIndustrielController::class, 'interventionsStore'])->name('interventions.store');

            Route::get('/plans', [EquipementIndustrielController::class, 'plansIndex'])->name('plans.index');
            Route::post('/plans', [EquipementIndustrielController::class, 'plansStore'])->name('plans.store');
            Route::put('/plans/{plan}', [EquipementIndustrielController::class, 'plansUpdate'])->name('plans.update');
            Route::delete('/plans/{plan}', [EquipementIndustrielController::class, 'plansDestroy'])->name('plans.destroy');
            Route::post('/plans/{plan}/executer', [EquipementIndustrielController::class, 'plansMarkExecuted'])->name('plans.executer');

            Route::get('/{equipementIndustriel}', [EquipementIndustrielController::class, 'show'])->name('show');
            Route::put('/{equipementIndustriel}', [EquipementIndustrielController::class, 'update'])->name('update');
            Route::delete('/{equipementIndustriel}', [EquipementIndustrielController::class, 'destroy'])->name('destroy');

            Route::post('/{equipementIndustriel}/documents', [EquipementIndustrielController::class, 'documentsStore'])->name('documents.store');
            Route::delete('/{equipementIndustriel}/documents/{document}', [EquipementIndustrielController::class, 'documentsDestroy'])->name('documents.destroy');
        });

    Route::middleware('check.role:responsable_maintenance,technicien,responsable_parc,magasinier')
        ->prefix('vehicules/pieces')
        ->name('vehicules.pieces.')
        ->group(function () {
            Route::get('/', [VehiculeController::class, 'piecesIndex'])->name('index');
            Route::post('/', [VehiculeController::class, 'piecesStore'])->name('store');
            Route::put('/{piece}', [VehiculeController::class, 'piecesUpdate'])->name('update');
            Route::delete('/{piece}', [VehiculeController::class, 'piecesDestroy'])->name('destroy');
        });

    Route::middleware('check.role:responsable_maintenance,technicien,responsable_parc,superviseur,user')
        ->prefix('vehicules')
        ->name('vehicules.')
        ->group(function () {
            Route::get('/', [VehiculeController::class, 'index'])->name('index');
            Route::post('/', [VehiculeController::class, 'store'])->name('store');

            Route::get('/dashboard', [VehiculeController::class, 'dashboard'])->name('dashboard');

            Route::get('/interventions', [VehiculeController::class, 'interventionsIndex'])->name('interventions.index');
            Route::post('/interventions', [VehiculeController::class, 'interventionsStore'])->name('interventions.store');

            Route::get('/plans', [VehiculeController::class, 'plansIndex'])->name('plans.index');
            Route::post('/plans', [VehiculeController::class, 'plansStore'])->name('plans.store');
            Route::put('/plans/{plan}', [VehiculeController::class, 'plansUpdate'])->name('plans.update');
            Route::delete('/plans/{plan}', [VehiculeController::class, 'plansDestroy'])->name('plans.destroy');
            Route::post('/plans/{plan}/executer', [VehiculeController::class, 'plansMarkExecuted'])->name('plans.executer');

            Route::get('/{vehicule}', [VehiculeController::class, 'show'])->name('show');
            Route::put('/{vehicule}', [VehiculeController::class, 'update'])->name('update');
            Route::delete('/{vehicule}', [VehiculeController::class, 'destroy'])->name('destroy');

            Route::post('/{vehicule}/documents', [VehiculeController::class, 'documentsStore'])->name('documents.store');
            Route::delete('/{vehicule}/documents/{document}', [VehiculeController::class, 'documentsDestroy'])->name('documents.destroy');
        });

    Route::middleware('check.role:responsable_maintenance,technicien,responsable_parc,magasinier')
        ->prefix('vehicules/pieces')
        ->name('vehicules.pieces.')
        ->group(function () {
            Route::get('/', [VehiculeController::class, 'piecesIndex'])->name('index');
            Route::post('/', [VehiculeController::class, 'piecesStore'])->name('store');
            Route::put('/{piece}', [VehiculeController::class, 'piecesUpdate'])->name('update');
            Route::delete('/{piece}', [VehiculeController::class, 'piecesDestroy'])->name('destroy');
        });

    Route::middleware('check.role:responsable_maintenance,technicien,magasinier')
        ->prefix('equipements-bureau/pieces')
        ->name('equipements-bureau.pieces.')
        ->group(function () {
            Route::get('/', [EquipementBureauController::class, 'piecesIndex'])->name('index');
            Route::post('/', [EquipementBureauController::class, 'piecesStore'])->name('store');
            Route::put('/{piece}', [EquipementBureauController::class, 'piecesUpdate'])->name('update');
            Route::delete('/{piece}', [EquipementBureauController::class, 'piecesDestroy'])->name('destroy');
        });

    Route::middleware('check.role:responsable_maintenance,technicien,superviseur,user')
        ->prefix('equipements-bureau')
        ->name('equipements-bureau.')
        ->group(function () {
            Route::get('/', [EquipementBureauController::class, 'index'])->name('index');
            Route::post('/', [EquipementBureauController::class, 'store'])->name('store');

            Route::get('/dashboard', [EquipementBureauController::class, 'dashboard'])->name('dashboard');

            Route::get('/interventions', [EquipementBureauController::class, 'interventionsIndex'])->name('interventions.index');
            Route::post('/interventions', [EquipementBureauController::class, 'interventionsStore'])->name('interventions.store');

            Route::get('/plans', [EquipementBureauController::class, 'plansIndex'])->name('plans.index');
            Route::post('/plans', [EquipementBureauController::class, 'plansStore'])->name('plans.store');
            Route::put('/plans/{plan}', [EquipementBureauController::class, 'plansUpdate'])->name('plans.update');
            Route::delete('/plans/{plan}', [EquipementBureauController::class, 'plansDestroy'])->name('plans.destroy');
            Route::post('/plans/{plan}/executer', [EquipementBureauController::class, 'plansMarkExecuted'])->name('plans.executer');

            Route::get('/{equipementBureau}', [EquipementBureauController::class, 'show'])->name('show');
            Route::put('/{equipementBureau}', [EquipementBureauController::class, 'update'])->name('update');
            Route::delete('/{equipementBureau}', [EquipementBureauController::class, 'destroy'])->name('destroy');

            Route::post('/{equipementBureau}/documents', [EquipementBureauController::class, 'documentsStore'])->name('documents.store');
            Route::delete('/{equipementBureau}/documents/{document}', [EquipementBureauController::class, 'documentsDestroy'])->name('documents.destroy');
        });

    Route::middleware('check.role:responsable_maintenance,responsable_parc,superviseur')
        ->prefix('fournisseurs')
        ->name('fournisseurs.')
        ->group(function () {
            Route::get('/', [FournisseurController::class, 'index'])->name('index');
            Route::post('/', [FournisseurController::class, 'store'])->name('store');
            Route::put('/{fournisseur}', [FournisseurController::class, 'update'])->name('update');
            Route::delete('/{fournisseur}', [FournisseurController::class, 'destroy'])->name('destroy');
        });

    // Point d'entrée générique (tuiles vers le stock de chaque module) : accessible à
    // quiconque a accès à au moins un stock de module, la page elle-même n'expose aucune
    // donnée sensible au-delà des liens.
    Route::middleware('check.role:responsable_maintenance,technicien,magasinier,responsable_parc')
        ->prefix('pieces')
        ->name('pieces.')
        ->group(function () {
            Route::get('/', [PieceController::class, 'index'])->name('index');
        });

    // Consommation de pièces sur une intervention : commun aux 3 modules équipement
    // (la table interventions est unique, indépendante du préfixe qui l'a créée).
    Route::post('/interventions/{intervention}/pieces', [PieceConsumptionController::class, 'store'])->name('interventions.pieces.store');
    Route::delete('/interventions/{intervention}/pieces/{interventionPiece}', [PieceConsumptionController::class, 'destroy'])->name('interventions.pieces.destroy');
    Route::get('/interventions/{intervention}/rapport', [InterventionRapportController::class, 'show'])->name('interventions.rapport');
    Route::put('/interventions/{intervention}/notes', [InterventionController::class, 'updateNotes'])->name('interventions.notes.update');

    Route::middleware('check.role:responsable_maintenance,magasinier,responsable_parc,superviseur')
        ->prefix('couts')
        ->name('couts.')
        ->group(function () {
            Route::get('/', [CoutEntretienController::class, 'index'])->name('index');
            Route::post('/', [CoutEntretienController::class, 'store'])->name('store');
            Route::get('/export', [CoutEntretienController::class, 'export'])->name('export');
            Route::delete('/{cout}', [CoutEntretienController::class, 'destroy'])->name('destroy');
        });

    Route::middleware('check.role:responsable_maintenance,responsable_parc,superviseur')
        ->prefix('indicateurs')
        ->name('indicateurs.')
        ->group(function () {
            Route::get('/', [IndicateurController::class, 'index'])->name('index');
            Route::post('/recalculer', [IndicateurController::class, 'recalculer'])->name('recalculer');
        });

    // Réservé aux admins de l'organisation (et super admin) : CheckRole laisse toujours
    // passer le rôle "admin" avant même de regarder cette liste, donc aucun autre rôle
    // ne peut y accéder ici.
    Route::middleware('check.role:admin')
        ->prefix('users')
        ->name('users.')
        ->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        });

    // Gestion des organisations elles-mêmes : réservé au super admin, indépendant du
    // système de modules/rôles (une organisation n'appartient à aucune organisation).
    Route::middleware('check.super_admin')
        ->prefix('organisations')
        ->name('organisations.')
        ->group(function () {
            Route::get('/', [OrganisationController::class, 'index'])->name('index');
            Route::post('/', [OrganisationController::class, 'store'])->name('store');
            Route::put('/{organisation}', [OrganisationController::class, 'update'])->name('update');
        });

    Route::post('/organisation/switch', [OrganisationSwitchController::class, 'switch'])
        ->middleware('check.super_admin')
        ->name('organisation.switch');
});

require __DIR__.'/auth.php';
