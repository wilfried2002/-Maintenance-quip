<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

        <!-- Ancien thème SB Admin 2 : conservé le temps de la migration vers Materio
             (voir plus bas) — encore utilisé par les pages/composants pas encore convertis. -->
        <link href="/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
        <link href="/vendor/sb-admin-2/sb-admin-2.scoped.css" rel="stylesheet">
        <link href="/vendor/sb-admin-2/overrides.css" rel="stylesheet">

        <!-- Thème Materio (Bootstrap 5) : nouveau système visuel, migration en cours page
             par page. Chargé APRÈS Vite/SB Admin 2 pour que ses classes priment. Scopé via
             .materio-item/.materio-page (scripts/scope-materio.mjs) pour ne jamais écraser
             Tailwind sur les pages pas encore converties — même mécanisme que SB Admin 2. -->
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
        <link href="/vendor/materio/css/iconify.css" rel="stylesheet">
        <link href="/vendor/materio/css/materio.scoped.css" rel="stylesheet">
    </head>
    <body class="font-sans antialiased">
        @inertia

        <!-- Scripts Materio, chargés en scripts classiques (pas type=module — voir plus bas).
             jquery.js/popper.js/node-waves.js/perfect-scrollbar.js EXCLUS : la sortie Vite de
             ces 4 fichiers contient encore des `import{...}from"..."` vers des chunks partagés
             non copiés (contrairement à bootstrap.js/menu.js/helpers.js/config.js/main.js, qui
             sont 100% autonomes — vérifié par grep sur chaque fichier). bootstrap.js embarque
             déjà Popper en interne, donc les dropdowns/tooltips fonctionnent sans popper.js à
             part. Perte assumée : effet ripple (node-waves) et scrollbar personnalisée
             (perfect-scrollbar) sur le menu — cosmétique, pas fonctionnel. -->
        <script src="/vendor/materio/js/helpers.js"></script>
        <script src="/vendor/materio/js/config.js"></script>
        <script src="/vendor/materio/js/bootstrap.js"></script>
        <script src="/vendor/materio/js/menu.js"></script>
    </body>
</html>
