# Système d'Indicateurs de Performance des Pièces

## Vue d'ensemble

Le système d'indicateurs de performance des pièces calcule automatiquement des métriques clés à partir des données réelles enregistrées lors des interventions d'entretien. **Aucune saisie manuelle n'est requise** — les indicateurs sont dérivés des consommations de pièces enregistrées dans les fiches d'intervention.

## Indicateurs Calculés

### 1. **Nombre de Remplacements**
- Nombre total de pièces consommées (quantité cumulée)
- Reflète l'intensité d'utilisation et d'usure
- **Exemple** : Une pièce remplacée 3 fois en 12 mois = 3 remplacements

### 2. **Durée de Vie Moyenne (jours)**
- Écart moyen en jours entre remplacements successifs
- Calculé par équipement (ne mélange pas les usures différentes)
- **Exemple** : Pièce remplacée le 01/01 et 15/02 = 45 jours de durée de vie

### 3. **MTBF - Mean Time Between Failures (heures)**
- Durée moyenne de service (heures d'utilisation) entre deux défaillances
- Mesure la fiabilité réelle de la pièce en conditions opérationnelles
- **Exemple** : MTBF = 500 heures = en moyenne, la pièce défaille tous les 500 h de service

### 4. **Taux de Défaillance (%)**
- Pourcentage de remplacements lors d'interventions correctives (pannes) vs préventives
- Indique la proactivité de la maintenance
- **Exemple** : Taux = 60% = 60% des remplacements sont dus à des pannes

### 5. **Coût Total de Remplacement (€)**
- Investissement cumulé en remplacements de cette pièce
- = Quantité × Prix Unitaire (pour chaque consommation)
- **Exemple** : 10 pièces × 50 € = 500 € d'investissement

## Architecture Technique

### Modèles & Tables

```
interventions
├── id, type_intervention (preventive/corrective)
├── equipementable_type, equipementable_id (polymorphe)
├── date_fin, date_planifiee, duree_heures
└── ...

intervention_pieces (pivot)
├── intervention_id, piece_id
├── quantite, prix_unitaire
└── timestamps

pieces
├── id, reference, designation
├── stock_qte, prix_unitaire_moyen
└── organisation_id

indicateurs_performance_pieces
├── id, piece_id
├── nombre_remplacements
├── duree_vie_moyenne_jours
├── mtbf_heures
├── taux_defaillance
├── cout_total_remplacement
├── derniere_maj
└── organisation_id
```

### Service de Calcul

**Classe** : `App\Services\IndicateurPerformanceCalculator`

```php
// Recalculer tous les indicateurs
$calculator = app(IndicateurPerformanceCalculator::class);
$count = $calculator->calculerTout();
```

**Processus** :
1. Récupérer toutes les pièces ayant au moins une consommation
2. Pour chaque pièce :
   - Récupérer les consommations (intervention_pieces + interventions)
   - Calculer nombre_remplacements = somme des quantités
   - Calculer duree_vie_moyenne_jours = écart moyen entre remplacements
   - Calculer mtbf_heures = heures de service entre défaillances
   - Calculer taux_defaillance = % interventions correctives
   - Calculer cout_total_remplacement = somme(quantité × prix)
3. Stocker dans `indicateurs_performance_pieces`

## Utilisation

### Depuis l'Interface Web

1. Accéder à `/indicateurs/pieces`
2. Voir le tableau de tous les indicateurs
3. Cliquer sur **"Recalculer maintenant"** pour mettre à jour (rôles requis : `responsable_maintenance`, `magasinier`)
4. La page recharge automatiquement après recalcul

### Depuis le Terminal

```bash
# Recalculer tous les indicateurs
php artisan indicateurs:recalculate-pieces

# Affiche le nombre de pièces mises à jour et la durée d'exécution
```

### Via Code PHP

```php
use App\Services\IndicateurPerformanceCalculator;

$calculator = app(IndicateurPerformanceCalculator::class);
$count = $calculator->calculerTout();

echo "Mis à jour : $count pièces";
```

### Via API REST

```bash
curl -X POST http://localhost:8000/indicateurs/pieces/recalculate \
  -H "X-CSRF-Token: {token}" \
  -H "Content-Type: application/json"

# Réponse JSON
{
  "success": true,
  "message": "✅ 15 pièces ont été mises à jour",
  "count": 15
}
```

## Cas d'Utilisation

### Identification des Pièces Critiques
- **Taux défaillance élevé** (> 50%) = problèmes de fiabilité ou maintenance réactive
- **Action** : Augmenter la fréquence de maintenance préventive

### Optimisation des Coûts
- **Coût total élevé** = besoin de négocier les prix ou envisager des alternatives
- **Action** : Contacter les fournisseurs ou évaluer des pièces de meilleure qualité

### Planification Préventive
- **Durée de vie stable** = permet de planifier les remplacements
- **MTBF élevé** = pièce fiable, maintenance préventive moins urgente

### Benchmarking
- Comparer les pièces similaires sur différents équipements
- Identifier les différences d'usure (maintenance insuffisante, conditions extrêmes)

## Champs de la Table

```sql
CREATE TABLE indicateurs_performance_pieces (
    id BIGINT PRIMARY KEY,
    organisation_id BIGINT,
    piece_id BIGINT,
    equipementable_type VARCHAR (nullable) -- NULL = indicateurs globaux
    equipementable_id BIGINT (nullable),   -- NULL = indicateurs globaux
    
    nombre_remplacements INT,              -- Total consommations
    duree_vie_moyenne_jours DECIMAL(10,2), -- Écart moyen (jours)
    mtbf_heures DECIMAL(10,2),             -- Service moyen (heures)
    taux_defaillance DECIMAL(5,4),         -- Ratio (0.0 à 1.0)
    cout_total_remplacement DECIMAL(12,2), -- Coût cumulé (€)
    
    derniere_maj DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Permissions

| Rôle | Accès |
|------|-------|
| `responsable_maintenance` | ✅ Visualiser + Recalculer |
| `magasinier` | ✅ Visualiser + Recalculer |
| `technicien` | ❌ Non autorisé |
| `user` | ❌ Non autorisé |

## Fréquence de Recalcul Recommandée

- **Mensuel** : Automatique via scheduler (voir `app/Console/Kernel.php`)
- **À la demande** : Via le bouton "Recalculer" dans l'interface
- **Après intervention** : Recalcul optionnel post-saisie

## Dépannage

### Aucun indicateur ne s'affiche
**Cause** : Aucune consommation de pièces enregistrée
**Solution** : 
1. Saisir au moins une intervention avec pièces consommées
2. Cliquer "Recalculer"

### Les valeurs ne se mettent pas à jour
**Cause** : Cachet potentiel ou requête lancée trop rapidement
**Solution** :
1. Vider le cache : `php artisan cache:clear`
2. Relancer le recalcul
3. Attendre 2-3 secondes pour le rechargement de page

### MTBF = NULL
**Cause** : Pas assez de données pour calculer (moins de 2 remplacements)
**Solution** : Enregistrer plus d'interventions avec consommations

## Intégration avec d'autres Modules

Les indicateurs sont utilisés pour :
- **Dashboard** : KPI des pièces critiques
- **Stock Pièces** : Recommandations de réapprovisionnement
- **Planification Maintenance** : Calibrage des fréquences
- **Rapports** : Analyses de performance et ROI

## Fichiers Impliqués

```
app/
├── Services/IndicateurPerformanceCalculator.php    # Service de calcul
├── Console/Commands/RecalculateIndicateursPieces.php  # Commande Artisan
├── Http/Controllers/
│   ├── IndicateurPerformanceController.php         # API recalcul
│   └── IndicateurPiecesController.php              # Affichage page
└── Models/IndicateurPerformancePiece.php           # Modèle

resources/js/Pages/Indicateurs/Pieces.vue           # Page Vue

routes/web.php                                       # Routes
```

## Exemples de Requêtes SQL

```sql
-- Pièces avec plus de 10 remplacements
SELECT p.reference, ipp.nombre_remplacements, ipp.taux_defaillance
FROM indicateurs_performance_pieces ipp
JOIN pieces p ON p.id = ipp.piece_id
WHERE ipp.nombre_remplacements > 10
ORDER BY ipp.nombre_remplacements DESC;

-- Pièces coûteuses (> 500€)
SELECT p.reference, ipp.cout_total_remplacement, ipp.taux_defaillance
FROM indicateurs_performance_pieces ipp
JOIN pieces p ON p.id = ipp.piece_id
WHERE ipp.cout_total_remplacement > 500
ORDER BY ipp.cout_total_remplacement DESC;

-- Pièces défaillantes (taux > 50%)
SELECT p.reference, ipp.taux_defaillance, ipp.nombre_remplacements
FROM indicateurs_performance_pieces ipp
JOIN pieces p ON p.id = ipp.piece_id
WHERE ipp.taux_defaillance > 0.5
ORDER BY ipp.taux_defaillance DESC;
```

---

**Version** : 1.0  
**Date** : 2026-08-18  
**Dernière mise à jour** : Création du système
