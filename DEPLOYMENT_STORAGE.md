# Guide de Déploiement - Configuration du Stockage

## Vue d'ensemble

L'application utilise Laravel Filesystem pour gérer le stockage des photos d'équipements. Ces fichiers sont stockés dans `storage/app/public/` et accessibles via les URLs `/storage/...`.

## Architecture du Stockage

```
storage/app/public/
├── equipements-bureau/      # Photos des équipements de bureau
├── equipements-industriels/ # Photos des équipements industriels
├── vehicules/              # Photos des véhicules
└── documents/              # Documents attachés
```

L'accès public est possible via un **lien symbolique** : `public/storage` → `storage/app/public/`

## Installation Locale

### 1. Création du lien symbolique

Après un clone/installation :

```bash
php artisan storage:ensure-link
```

Ou avec l'option `--refresh` pour recréer un lien existant :

```bash
php artisan storage:ensure-link --refresh
```

**Notes Windows :**
- La commande utilise `mklink /J` (junction) qui fonctionne sans droits administrateur
- Si le lien existe déjà, utilisez `--refresh` pour le recréer

**Notes Unix/Linux :**
- Utilise `symlink()` natif
- Assurez-vous que le répertoire `public/` est accessible en écriture

### 2. Vérification

Après avoir créé le lien, vérifiez que les fichiers sont accessibles :

```bash
# Vérifier que le lien existe
ls -la public/storage

# Ou sur Windows
dir public\storage

# Vérifier que les fichiers sont visibles
ls public/storage/equipements-bureau/
```

## Déploiement en Production

### 1. Configuration d'Apache

Assurez-vous que Apache peut suivre les lien symboliques. Dans `VirtualHost` ou `.htaccess` :

```apache
<Directory /var/www/html>
    Options FollowSymLinks
</Directory>
```

### 2. Configuration de Nginx

Nginx suit automatiquement les liens symboliques (pas de configuration supplémentaire).

### 3. Automatisation du lien symbolique

**Méthode 1 : Via Artisan (Recommandé)**

Ajoutez à votre script de déploiement (deploy.sh, Capistrano, etc.) :

```bash
php artisan storage:ensure-link
```

ou utilisez la commande officielle Laravel :

```bash
php artisan storage:link
```

**Méthode 2 : Lien symbolique manuel**

```bash
# Unix/Linux
ln -s /var/www/html/storage/app/public /var/www/html/public/storage

# Windows (Command Prompt as Administrator)
mklink /J C:\path\to\public\storage C:\path\to\storage\app\public

# Windows (PowerShell as Administrator)
New-Item -ItemType Junction -Path "C:\path\to\public\storage" -Target "C:\path\to\storage\app\public"
```

**Méthode 3 : Nginx avec alias (Alternative sans symlink)**

Si les liens symboliques ne sont pas disponibles ou désirés, utilisez un alias Nginx :

```nginx
location /storage {
    alias /var/www/html/storage/app/public;
    expires 30d;
}
```

### 4. Permissions de fichier

En production, assurez-vous que le répertoire de stockage a les bonnes permissions :

```bash
# Propriétaire et groupe corrects
chown -R www-data:www-data /var/www/html/storage

# Permissions correctes
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/public/storage  # Si c'est un lien symbolique
```

### 5. Espace disque et maintenance

Mettez en place un système de nettoyage des fichiers orphelins :

```bash
# À ajouter dans le crontab
0 3 * * * cd /var/www/html && php artisan storage:cleanup-orphaned
```

## Optimisation des performances

### Cache des headers HTTP

Configurez Laravel pour mettre en cache les images statiques :

```php
// config/filesystems.php - Dans le disque 'public'
'url' => env('APP_URL') . '/storage',
'visibility' => 'public',
```

Ajoutez à votre `.htaccess` ou configuration serveur :

```apache
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Header set Cache-Control "max-age=2592000"
</FilesMatch>
```

### Conversion d'images

Pour optimiser les performances, considérez :

- **WebP conversion** : Convertir les uploads en WebP (librairie Intervention Image)
- **Redimensionnement** : Réduire les images au moment de l'upload
- **Compression** : Compresser les images avant stockage

## Dépannage

### Les photos ne s'affichent pas

1. Vérifiez que le lien symbolique existe :
   ```bash
   php artisan storage:ensure-link
   ```

2. Vérifiez que les fichiers existent dans le stockage :
   ```bash
   ls storage/app/public/equipements-bureau/
   ```

3. Vérifiez les logs Laravel :
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. Vérifiez les permissions :
   ```bash
   ls -la public/storage/
   ls -la storage/app/public/
   ```

### Lien symbolique cassé après déploiement

Le lien symbolique peut être cassé si les chemins changent. Solution :

```bash
php artisan storage:ensure-link --refresh
```

### Erreur "permission denied" lors du déploiement

Assurez-vous que l'utilisateur du serveur web peut créer des liens symboliques :

```bash
# Vérifiez la configuration SELinux (si applicable)
getenforce

# Ou changez les permissions du répertoire public
chmod 755 /var/www/html/public
```

## Variables d'environnement

Assurez-vous que ces variables sont configurées correctement dans `.env` :

```env
APP_URL=https://example.com
FILESYSTEM_DISK=public
```

La variable `APP_URL` est utilisée dans `config/filesystems.php` pour générer les URLs des fichiers stockés.

## Intégration CDN (Optional)

Pour un déploiement haute performance, configurez un CDN :

```php
// config/filesystems.php
'disks' => [
    'public' => [
        // ... autres configurations
        'url' => env('CDN_URL', rtrim(env('APP_URL'), '/') . '/storage'),
    ],
],
```

Puis dans `.env` :

```env
CDN_URL=https://cdn.example.com
```

## Résumé des commandes essentielles

```bash
# Installation locale
php artisan storage:ensure-link

# Rafraîchir un lien existant
php artisan storage:ensure-link --refresh

# Commande Laravel officielle
php artisan storage:link

# Vérifier les fichiers
ls storage/app/public/
ls public/storage/
```

---

**Date de dernière mise à jour** : 2026-08-18
**Auteur** : GitHub Copilot
