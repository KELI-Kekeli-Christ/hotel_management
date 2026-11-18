<p align="center">
  <a href="README.md"><b>English</b></a>
  &nbsp;·&nbsp;
  <a href="README.fr.md">Français</a>
  &nbsp;·&nbsp;
  <a href="README.es.md">Español</a>
</p>

# Système de Gestion Hôtelière

Application web complète de gestion hôtelière développée avec Laravel (backend) et Nuxt.js (frontend). Le système permet la gestion des réservations, des chambres, des services, des factures, des paiements et propose un système d'authentification et d'autorisation basé sur les rôles.

## Table des matières

- [Architecture](#architecture)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Démarrage](#démarrage)
- [Fonctionnalités](#fonctionnalités)
- [Rôles et Permissions](#rôles-et-permissions)
- [Structure du Projet](#structure-du-projet)
- [Technologies Utilisées](#technologies-utilisées)
- [API Endpoints](#api-endpoints)
- [Jobs et Queues](#jobs-et-queues)

## Architecture

Le projet est divisé en deux applications distinctes :

- **Backend (Laravel)** : API REST gérant la logique métier, l'authentification JWT, la base de données, et les traitements asynchrones
- **Frontend (Nuxt.js)** : Interface utilisateur moderne et réactive avec Nuxt UI

## Prérequis

### Backend

- PHP >= 8.1
- Composer
- MySQL ou PostgreSQL
- Extensions PHP : BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML, GD/Imagick (pour le traitement d'images)

### Frontend

- Node.js >= 18
- npm ou pnpm

## Installation

### 1. Cloner le dépôt

```bash
git clone <repository-url>
cd hotel
```

### 2. Installation Backend

```bash
cd projet-hotel-back

# Installer les dépendances PHP
composer install

# Copier le fichier d'environnement
cp .env.example .env

# Générer la clé d'application
php artisan key:generate

# Générer la clé JWT
php artisan jwt:secret

# Configurer la base de données dans .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=hotel_db
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Créer la base de données
php artisan migrate

# Créer le lien symbolique pour le stockage
php artisan storage:link

# Exécuter les seeders (rôles, permissions, données de test)
php artisan db:seed

# Créer la table des jobs pour les queues
php artisan queue:table
php artisan migrate
```

### 3. Installation Frontend

```bash
cd projet-hotel-front

# Installer les dépendances
npm install
# ou
pnpm install
```

## Configuration

### Variables d'environnement Backend (.env)

```env
APP_NAME="Hotel Management System"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_db
DB_USERNAME=root
DB_PASSWORD=

# JWT Configuration
JWT_SECRET=
JWT_TTL=60

# FedaPay Configuration (pour les paiements)
FEDAPAY_API_KEY=your_api_key
FEDAPAY_ENVIRONMENT=sandbox

# Queue Configuration
QUEUE_CONNECTION=database
```

### Variables d'environnement Frontend

Créer un fichier `.env` dans `projet-hotel-front` :

```env
NUXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
NUXT_PUBLIC_API_BASE_URL2=http://localhost:8000
```

## Démarrage

### Méthode 1 : Script automatique

Utiliser le script `start-dev.sh` à la racine du projet pour démarrer les deux serveurs simultanément :

```bash
chmod +x start-dev.sh
./start-dev.sh
```

Ce script :
- Libère automatiquement le port 8000 s'il est occupé
- Démarre le serveur PHP Laravel sur le port 8000
- Démarre le serveur de développement Nuxt.js
- Affiche les logs des deux serveurs dans le terminal

### Méthode 2 : Démarrage manuel

**Terminal 1 - Backend :**
```bash
cd projet-hotel-back
php artisan serve --port=8000
```

**Terminal 2 - Frontend :**
```bash
cd projet-hotel-front
npm run dev
# ou
pnpm dev
```

### Accès à l'application

- **Frontend** : http://localhost:3000
- **Backend API** : http://localhost:8000/api
- **Landing Page** : http://localhost:3000/home

### Compte administrateur par défaut

Après avoir exécuté les seeders, un compte Super Admin est créé :

- **Email** : admin@hotel.com
- **Mot de passe** : admin

**Important** : Changez ce mot de passe en production.

## Fonctionnalités

### Gestion des Utilisateurs

- Inscription et authentification des clients
- Gestion des membres du personnel (Réceptionniste, Service Manager, Économiste)
- Système de rôles et permissions granulaire
- Profils utilisateurs personnalisables

### Gestion des Chambres

- Ajout, modification et suppression de chambres
- Types de chambres (Simple, Double, Suite, etc.)
- Gestion de la disponibilité automatique
- Images et descriptions des chambres
- Prix par type de chambre

### Gestion des Réservations

- Création de réservations par les clients ou le personnel
- Vérification automatique de la disponibilité
- Statuts des réservations : pending, confirmed, checked_out, cancelled, failed, expired
- Libération automatique des chambres après le départ
- Blocage des réservations chevauchantes

### Gestion des Services

- Menu pour Bar, Restaurant et Spa
- Commandes de services par les clients
- Gestion des disponibilités des services
- Historique des commandes

### Facturation

- Génération automatique de factures lors de la confirmation de réservation
- Intégration des commandes de services dans les factures
- Mise à jour des factures avec les nouvelles commandes
- Export PDF des factures avec logo et informations de l'hôtel
- Tampon "Payé" personnalisable pour les factures payées
- Visualisation des factures côté client et admin

### Paiements

- Intégration avec FedaPay
- Paiements sécurisés en ligne
- Webhooks pour la gestion des statuts de paiement
- Traitement asynchrone des paiements

### Configuration Hôtel

- Paramètres personnalisables (nom, adresse, téléphone, email)
- Upload de logo
- Upload de tampon "Payé" pour les factures
- Configuration accessible depuis l'interface admin

### Tableau de Bord

- Statistiques en temps réel (revenus, réservations, clients)
- Graphiques de revenus
- Transactions récentes
- Vues filtrées par période

### Landing Page Publique

- Affichage des informations de l'hôtel
- Liste des chambres disponibles avec images et prix
- Catalogue des services disponibles
- Interface publique sans authentification

## Rôles et Permissions

Le système utilise Spatie Laravel Permission pour gérer les rôles et permissions.

### Rôles disponibles

1. **Client**
   - Visualiser et modifier son propre profil
   - Lister ses propres réservations
   - Créer des réservations
   - Commander des services
   - Visualiser et télécharger ses factures

2. **Service-Manager**
   - Accéder au tableau de bord
   - Gérer les menus (Bar, Restaurant, Spa)
   - Gérer les commandes de services
   - Visualiser les statistiques des services

3. **Receptionist**
   - Accéder au tableau de bord
   - Gérer les utilisateurs
   - Gérer les chambres
   - Gérer toutes les réservations
   - Générer et visualiser les factures
   - Marquer les factures comme payées

4. **Économiste**
   - Accéder au tableau de bord
   - Lister toutes les factures
   - Visualiser les factures

5. **Super-Admin**
   - Accès complet à toutes les fonctionnalités
   - Gestion des rôles et permissions
   - Configuration de l'hôtel
   - Gestion complète du système

### Permissions disponibles

- Utilisateurs : `user-list`, `user-create`, `user-view`, `user-update`, `user-delete`, `user-update-role`, `user-view-self`, `user-update-self`
- Chambres : `room-list`, `room-create`, `room-view`, `room-update`, `room-delete`
- Réservations : `reservation-list-all`, `reservation-create-for-others`, `reservation-view-any`, `reservation-update-any`, `reservation-delete-any`, `reservation-list-own`, `reservation-create-own`
- Services : `service-menu-manage`, `service-order-manage`, `service-stats-view`
- Factures : `invoice-generate`, `invoice-list`, `invoice-view`, `invoice-update-status`
- Système : `system-role-manage`, `system-dashboard-view`

## Structure du Projet

```
hotel/
├── projet-hotel-back/          # Backend Laravel
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/    # Contrôleurs API
│   │   │   ├── Middleware/     # Middlewares personnalisés
│   │   │   └── Requests/       # Form Requests de validation
│   │   ├── Models/             # Modèles Eloquent
│   │   ├── Policies/           # Policies d'autorisation
│   │   ├── Jobs/               # Jobs asynchrones
│   │   └── Helpers/            # Classes helper
│   ├── database/
│   │   ├── migrations/         # Migrations de base de données
│   │   └── seeders/            # Seeders pour données de test
│   ├── resources/
│   │   └── views/
│   │       └── invoices/       # Template Blade pour factures PDF
│   ├── routes/
│   │   └── api.php             # Routes API
│   └── config/                 # Fichiers de configuration
│
├── projet-hotel-front/         # Frontend Nuxt.js
│   ├── app/
│   │   ├── components/         # Composants Vue réutilisables
│   │   ├── pages/              # Pages de l'application
│   │   ├── layouts/            # Layouts
│   │   ├── stores/             # Stores Pinia
│   │   ├── middleware/         # Middlewares de routage
│   │   ├── plugins/            # Plugins Nuxt
│   │   └── types/              # Types TypeScript
│   ├── server/
│   │   └── api/                # API routes Nuxt (proxy)
│   └── nuxt.config.ts          # Configuration Nuxt
│
└── start-dev.sh                # Script de démarrage des serveurs
```

## Technologies Utilisées

### Backend

- **Laravel 10** : Framework PHP
- **Laravel Sanctum** : Authentification API
- **PHP JWT Auth** : Authentification JWT
- **Spatie Laravel Permission** : Gestion des rôles et permissions
- **Laravel DomPDF** : Génération de factures PDF
- **FedaPay PHP SDK** : Intégration de paiement
- **Laravel Queues** : Traitement asynchrone

### Frontend

- **Nuxt 3** : Framework Vue.js
- **Nuxt UI** : Composants UI pré-construits
- **Pinia** : Gestion d'état
- **Axios** : Client HTTP
- **Unovis** : Visualisations de données
- **VueUse** : Utilitaires Vue composables
- **TypeScript** : Typage statique

## API Endpoints

### Authentification

- `POST /api/auth/register` - Inscription
- `POST /api/auth/login` - Connexion
- `POST /api/auth/logout` - Déconnexion
- `POST /api/auth/refresh` - Rafraîchir le token

### Utilisateurs

- `GET /api/users` - Liste des utilisateurs
- `GET /api/users/{id}` - Détails d'un utilisateur
- `POST /api/users` - Créer un utilisateur
- `PUT /api/users/{id}` - Mettre à jour un utilisateur
- `DELETE /api/users/{id}` - Supprimer un utilisateur

### Chambres

- `GET /api/chambres` - Liste des chambres
- `GET /api/chambres/{id}` - Détails d'une chambre
- `POST /api/chambres` - Créer une chambre
- `PUT /api/chambres/{id}` - Mettre à jour une chambre
- `DELETE /api/chambres/{id}` - Supprimer une chambre

### Réservations

- `GET /api/reservations` - Liste des réservations
- `GET /api/reservations/{id}` - Détails d'une réservation
- `POST /api/reservations` - Créer une réservation
- `PUT /api/reservations/{id}` - Mettre à jour une réservation
- `DELETE /api/reservations/{id}` - Supprimer une réservation

### Factures

- `GET /api/invoices` - Liste des factures
- `GET /api/invoices/{id}` - Détails d'une facture
- `GET /api/invoices/{id}/pdf` - Télécharger le PDF
- `POST /api/invoices/{id}/update-from-orders` - Mettre à jour avec nouvelles commandes
- `PUT /api/invoices/{id}/status` - Mettre à jour le statut

### Services

- `GET /api/menu-items` - Liste des services (public)
- `GET /api/menu-items/{id}` - Détails d'un service
- `POST /api/menu-items` - Créer un service
- `PUT /api/menu-items/{id}` - Mettre à jour un service
- `DELETE /api/menu-items/{id}` - Supprimer un service

### Commandes

- `GET /api/orders` - Liste des commandes
- `POST /api/orders` - Créer une commande
- `PUT /api/orders/{id}/status` - Mettre à jour le statut

### Configuration

- `GET /api/hotel-config` - Récupérer la configuration (public)
- `PUT /api/hotel-config` - Mettre à jour la configuration

### Paiements

- `POST /api/payments/initiate` - Initier un paiement
- `POST /api/payments/webhook` - Webhook FedaPay
- `GET /api/payments/{id}/status` - Statut d'un paiement

## Jobs et Queues

Le système utilise Laravel Queues pour les traitements asynchrones.

### Job : ReleaseRoomAfterCheckout

Libère automatiquement une chambre après la date de départ de la réservation. Le job est programmé pour s'exécuter un jour après la date de départ.

**Configuration :**
```bash
# Démarrer le worker de queue
php artisan queue:work

# En production, utiliser un process manager comme Supervisor
```

### Migration des Jobs

La table `jobs` est créée automatiquement lors de l'exécution de `php artisan queue:table` et `php artisan migrate`.

## Production

### Build Frontend

```bash
cd projet-hotel-front
npm run build
# ou
pnpm build
```

### Optimisation Backend

```bash
cd projet-hotel-back

# Optimiser l'autoloader
composer install --optimize-autoloader --no-dev

# Cache de configuration
php artisan config:cache

# Cache des routes
php artisan route:cache

# Cache des vues
php artisan view:cache
```

### Variables d'environnement Production

Assurez-vous de définir :
- `APP_ENV=production`
- `APP_DEBUG=false`
- `QUEUE_CONNECTION=database` ou `redis`
- Configurer les variables FedaPay en production

### Process Manager pour Queues

Pour gérer les workers de queue en production, utilisez Supervisor ou un système similaire pour maintenir les processus actifs.

## Support

Pour toute question ou problème, veuillez consulter la documentation ou ouvrir une issue sur le dépôt.

## Licence

All rights reserved.

