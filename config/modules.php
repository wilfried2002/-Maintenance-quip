<?php

// Source unique de vérité pour le système de permissions par module.
//
// - `list` : les modules affichables sous forme de cases à cocher dans l'édition utilisateur.
// - `role_defaults` : ce qu'un rôle donne par défaut. Utilisé UNIQUEMENT pour pré-cocher la
//   grille dans l'UI — ce n'est pas ce tableau qui protège les routes (voir CheckRole), donc
//   une imprécision ici n'ouvre aucune faille, elle change juste l'état initial des cases.
// - `route_module_map` : préfixe de nom de route -> clé de module, utilisé par CheckRole pour
//   savoir quel module concerne la requête en cours, sans toucher à routes/web.php.

return [

    // `color` : thème par module (utilisé côté frontend pour teinter menu, en-têtes, boutons).
    // `route` : nom de route Inertia du menu principal du module.
    'list' => [
        'equipements_industriels' => ['label' => 'Équipements industriels', 'icon' => 'ri-building-4-line', 'color' => 'orange', 'route' => 'equipements-industriels.dashboard'],
        'parc_automobile'         => ['label' => 'Parc automobile', 'icon' => 'ri-truck-line', 'color' => 'green', 'route' => 'vehicules.dashboard'],
        'equipement_bureau'       => ['label' => 'Équipement de bureau', 'icon' => 'ri-computer-line', 'color' => 'blue', 'route' => 'equipements-bureau.dashboard'],
        'pieces_stock'            => ['label' => 'Pièces détachées / Stock', 'icon' => 'ri-archive-2-line', 'color' => 'amber', 'route' => 'pieces.index'],
        'couts_entretien'         => ['label' => 'Coûts d\'entretien', 'icon' => 'ri-receipt-line', 'color' => 'teal', 'route' => 'couts.index'],
        'indicateurs'             => ['label' => 'Indicateurs de performance', 'icon' => 'ri-line-chart-line', 'color' => 'purple', 'route' => 'indicateurs.index'],
        'fournisseurs'            => ['label' => 'Fournisseurs', 'icon' => 'ri-store-2-line', 'color' => 'slate', 'route' => 'fournisseurs.index'],
        'utilisateurs'            => ['label' => 'Utilisateurs', 'icon' => 'ri-team-line', 'color' => 'slate', 'route' => 'users.index'],
    ],

    // Les interventions n'ont pas de permission propre : elles vivent à l'intérieur de
    // chaque module équipement (mêmes routes, même préfixe) et héritent donc de son droit.
    'role_defaults' => [
        'responsable_maintenance' => ['equipements_industriels', 'parc_automobile', 'equipement_bureau', 'pieces_stock', 'couts_entretien', 'indicateurs', 'fournisseurs'],
        'technicien'              => ['equipements_industriels', 'parc_automobile', 'equipement_bureau', 'pieces_stock'],
        'magasinier'              => ['pieces_stock', 'couts_entretien'],
        'responsable_parc'        => ['parc_automobile', 'pieces_stock', 'couts_entretien', 'indicateurs', 'fournisseurs'],
        'superviseur'             => ['equipements_industriels', 'parc_automobile', 'equipement_bureau', 'indicateurs', 'couts_entretien', 'fournisseurs'],
        'user'                    => ['equipements_industriels', 'parc_automobile', 'equipement_bureau'],
    ],

    'route_module_map' => [
        // Sous-groupes "pieces" à faire correspondre AVANT le préfixe général du module
        // (premier préfixe qui matche gagne) : leur permission est pieces_stock, pas la
        // permission générale du module équipement.
        'equipements-industriels.pieces.' => 'pieces_stock',
        'vehicules.pieces.'               => 'pieces_stock',
        'equipements-bureau.pieces.'      => 'pieces_stock',
        'equipements-industriels.'        => 'equipements_industriels',
        'vehicules.'                      => 'parc_automobile',
        'equipements-bureau.'             => 'equipement_bureau',
        'pieces.'                         => 'pieces_stock',
        'couts.'                          => 'couts_entretien',
        'indicateurs.'                    => 'indicateurs',
        'fournisseurs.'                   => 'fournisseurs',
        'users.'                          => 'utilisateurs',
    ],

];
