# Installation de l'extension PHP GD

L'extension PHP GD est requise pour générer les PDF avec des images (comme le tampon "PAYÉ").

## Installation sur Ubuntu/Debian

Exécutez la commande suivante :

```bash
sudo apt-get update
sudo apt-get install -y php-gd
```

Pour PHP 8.3 spécifiquement :

```bash
sudo apt-get install -y php8.3-gd
```

## Vérification de l'installation

Après l'installation, vérifiez que GD est bien installé :

```bash
php -m | grep gd
```

Vous devriez voir `gd` dans la liste.

Ou vérifiez avec :

```bash
php -r "echo extension_loaded('gd') ? 'GD est installé' : 'GD n\'est pas installé';"
```

## Redémarrage du serveur

Après l'installation, redémarrez le serveur PHP (si vous utilisez PHP-FPM) :

```bash
sudo systemctl restart php8.3-fpm
```

Ou si vous utilisez le serveur de développement intégré de Laravel :

```bash
# Arrêtez le serveur (Ctrl+C) puis relancez
php artisan serve
```

