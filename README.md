# Maintenance Équip — GMAO multi-organisations

Application de **gestion de maintenance assistée par ordinateur** (GMAO) multi-tenant :
équipements industriels, parc automobile et équipement de bureau — interventions,
maintenance préventive, stock de pièces, coûts d'entretien et indicateurs de performance.

## Stack

- **Backend** : Laravel 12 (PHP ≥ 8.2), MySQL (SQLite pour les tests), queues/session en base
- **Frontend** : Inertia 2 + Vue 3 + Tailwind CSS + template Materio (Bootstrap 5 scopé)
- **PDF** : barryvdh/laravel-dompdf · **Recherche** : Ziggy + axios

## Fonctionnalités

| Domaine | Détail |
|---|---|
| **Multi-organisation (SaaS)** | Code organisation à la connexion, cloisonnement strict des données par scope global Eloquent (`BelongsToOrganisation`), devise par organisation |
| **Rôles** (7) | admin, responsable maintenance, technicien, magasinier, responsable parc, superviseur, user — + **permissions fines par module** (grille Accordé/Révoqué dans la page Utilisateurs) |
| **3 modules équipement** | Fiche complète (photo, criticité, garantie, fournisseur, documents PDF/photos) + tableau de bord par module |
| **Interventions** | Préventive/corrective/prédictive, statuts, priorités, technicien, consommation de pièces (prix figé, restitution au stock), notes terrain, **rapport PDF** |
| **Maintenance préventive** | Plans par jours ou kilométrage, génération automatique des interventions dues (cron), détection des retards |
| **Stock de pièces** | Cloisonné par module, seuil d'alerte, notifications stock bas (base + e-mail), indicateurs : taux de défaillance, durée de vie moyenne, coût total |
| **Coûts** | Main d'œuvre journalisée automatiquement, prestations externes, agrégats par type/équipement, **export CSV** (Excel) |
| **Notifications** | Alertes plans en retard et stock bas, auto-résolution, horodatage relatif |
| **Recherche globale** | Équipements, interventions, pièces — respecte les permissions par module |

## Installation (développement)

```bash
composer install
cp .env.example .env        # puis renseigner la base MySQL
php artisan key:generate
php artisan migrate --seed  # crée le super admin + une organisation de démo
npm install && npm run build
php artisan serve
```

Comptes du seeder : `admin@maintenance.local` (super admin, sans code) et
`admin.org@maintenance.local` / `parc@maintenance.local` (code `DEMO01`).

### Développement continu

```bash
composer dev   # serveur + queue + logs + Vite en parallèle
composer test  # suite PHPUnit (SQLite en mémoire)
```

## Tâches planifiées (obligatoire en production)

```bash
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

Sans cron : pas de génération de maintenance préventive (`maintenance:generate`,
quotidien), pas d'alertes stock bas / plans en retard (`alertes:generer`, horaire),
pas de recalcul des indicateurs (`indicateurs:calculer`, quotidien).

## Sécurité — points clés

- Cloisonnement SaaS par **scope global** au niveau du modèle (anti-IDOR, testé bout en bout)
- Fichiers métier (photos, documents) sur **disque privé**, servis par une route authentifiée
  qui vérifie organisation + module (`/fichiers/...`)
- `is_super_admin` non mass-assignable ; connexion à session unique ; rate limiting login
- Voir `DEPLOYMENT_CHECKLIST.md` pour la checklist production complète (APP_DEBUG,
  SESSION_SECURE_COOKIE, SMTP, `fichiers:prive`, etc.)

## Tests

```bash
composer test
```

Couverture notable : isolation multi-organisation (IDOR), middleware de rôles,
permissions par module, accès aux fichiers privés, accès aux interventions,
consommation de pièces (verrou de stock), alertes, recherche, auth (inscription
à activation comprise).

## Déploiement

Consulter **`DEPLOYMENT_CHECKLIST.md`** (checklist sécurité + script type) et
**`DEPLOYMENT_STORAGE.md`** (stockage, permissions, symlink). Gabarit `.env` :
**`.env.production.example`**.
