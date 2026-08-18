<?php

namespace App\Console\Commands;

use App\Models\Piece;
use App\Models\PlanMaintenance;
use App\Models\User;
use App\Notifications\PlanMaintenanceEnRetard;
use App\Notifications\StockBas;
use App\Services\RoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GenererAlertes extends Command
{
    protected $signature = 'alertes:generer';

    protected $description = 'Notifie les utilisateurs concernés des plans de maintenance en retard et des pièces en stock bas, et résout les alertes obsolètes';

    public function handle(): int
    {
        $plansCount = $this->genererAlertesPlans();
        $stockCount = $this->genererAlertesStock();

        $this->info("{$plansCount} alerte(s) de plan en retard, {$stockCount} alerte(s) de stock bas générée(s).");

        return self::SUCCESS;
    }

    /**
     * Cache des destinataires par "module:organisation_id", pour ne recalculer
     * qu'une fois par organisation malgré plusieurs plans/pièces à traiter.
     *
     * @var array<string, Collection<int, User>>
     */
    private array $destinatairesCache = [];

    private function destinataires(string $module, int $organisationId): Collection
    {
        $cle = "{$module}:{$organisationId}";

        return $this->destinatairesCache[$cle] ??= RoleService::usersWithModuleAccess($module, $organisationId);
    }

    private function genererAlertesPlans(): int
    {
        $moduleParClasse = [
            \App\Models\EquipementIndustriel::class => 'equipements_industriels',
            \App\Models\Vehicule::class => 'parc_automobile',
            \App\Models\EquipementBureau::class => 'equipement_bureau',
        ];

        $plans = PlanMaintenance::where('actif', true)->with('equipementable')->get();
        $plansEnRetard = $plans->filter(fn (PlanMaintenance $plan) => $plan->equipementable && $plan->en_retard);
        $idsEnRetard = $plansEnRetard->pluck('id')->all();

        $sent = 0;

        foreach ($plansEnRetard as $plan) {
            $module = $moduleParClasse[$plan->equipementable::class] ?? null;
            if (!$module || !$plan->organisation_id) {
                continue;
            }

            // Cloisonnement : seuls les utilisateurs de LA MÊME organisation que le plan
            // (+ les super admins, gérés par usersWithModuleAccess) sont notifiés.
            foreach ($this->destinataires($module, $plan->organisation_id) as $user) {
                if ($this->dejaAlerte($user, 'plan_id', $plan->id)) {
                    continue;
                }

                $user->notify(new PlanMaintenanceEnRetard($plan));
                $sent++;
            }
        }

        $this->resoudreAlertesObsoletes(User::all(), 'plan_id', $idsEnRetard);

        return $sent;
    }

    private function genererAlertesStock(): int
    {
        $piecesEnSousStock = Piece::whereColumn('stock_qte', '<=', 'stock_min')->get();
        $idsEnSousStock = $piecesEnSousStock->pluck('id')->all();

        $sent = 0;

        foreach ($piecesEnSousStock as $piece) {
            if (!$piece->organisation_id) {
                continue;
            }

            foreach ($this->destinataires('pieces_stock', $piece->organisation_id) as $user) {
                if ($this->dejaAlerte($user, 'piece_id', $piece->id)) {
                    continue;
                }

                $user->notify(new StockBas($piece));
                $sent++;
            }
        }

        $this->resoudreAlertesObsoletes(User::all(), 'piece_id', $idsEnSousStock);

        return $sent;
    }

    private function dejaAlerte(User $user, string $cle, int $id): bool
    {
        return $user->unreadNotifications()
            ->where("data->{$cle}", $id)
            ->exists();
    }

    /**
     * Marque comme lues les alertes non lues dont la condition (plan encore en retard /
     * pièce encore en sous-stock) ne tient plus, pour ne pas laisser une alerte résolue
     * traîner indéfiniment dans la liste.
     *
     * @param  Collection<int, User>  $users
     * @param  array<int, int>  $idsEncoreValides
     */
    private function resoudreAlertesObsoletes(Collection $users, string $cle, array $idsEncoreValides): void
    {
        foreach ($users as $user) {
            $user->unreadNotifications()
                ->whereNotNull("data->{$cle}")
                ->get()
                ->each(function ($notification) use ($cle, $idsEncoreValides) {
                    $id = $notification->data[$cle] ?? null;
                    if ($id !== null && !in_array($id, $idsEncoreValides, true)) {
                        $notification->markAsRead();
                    }
                });
        }
    }
}
