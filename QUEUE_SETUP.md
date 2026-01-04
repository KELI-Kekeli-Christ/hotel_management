# Configuration des Queues pour la Libération Automatique des Chambres

## Fonctionnalité

Lorsqu'une facture est générée, un job est automatiquement planifié pour libérer la chambre (rendre disponible) **1 jour après** la génération de la facture.

## Configuration Requise

### 1. Exécuter la Migration

```bash
cd /home/christ/hotel/projet-hotel-back
php artisan migrate
```

Cette migration crée la table `jobs` nécessaire pour stocker les jobs en attente.

### 2. Configurer le Driver de Queue

Dans le fichier `.env` de votre projet backend, assurez-vous d'avoir :

```env
QUEUE_CONNECTION=database
```

Ou pour un développement local simple :
```env
QUEUE_CONNECTION=sync
```

**Note**: Avec `sync`, les jobs s'exécutent immédiatement (pas de délai). Pour le délai de 24h, utilisez `database`.

### 3. Démarrer le Worker de Queue

Pour que les jobs différés fonctionnent, vous devez démarrer un worker de queue :

```bash
cd /home/christ/hotel/projet-hotel-back
php artisan queue:work
```

Pour exécuter en arrière-plan :
```bash
php artisan queue:work > /dev/null 2>&1 &
```

### 4. (Optionnel) Utiliser Supervisor pour le Worker

Pour une production, utilisez Supervisor pour maintenir le worker actif :

```bash
# Installer supervisor
sudo apt-get install supervisor

# Créer la configuration
sudo nano /etc/supervisor/conf.d/hotel-queue-worker.conf
```

Contenu du fichier :
```ini
[program:hotel-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/christ/hotel/projet-hotel-back/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=christ
numprocs=1
redirect_stderr=true
stdout_logfile=/home/christ/hotel/projet-hotel-back/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Puis :
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start hotel-queue-worker:*
```

## Comment ça fonctionne

1. Une facture est générée via `InvoiceController::generateForReservation()`
2. Un job `ReleaseRoomAfterInvoice` est dispatché avec un délai de 24 heures
3. Le worker de queue exécute le job après 24 heures
4. Le job vérifie s'il y a d'autres réservations actives sur la chambre
5. Si non, la chambre est libérée (disponibilite = true)

## Vérification

Pour voir les jobs en attente :
```bash
php artisan queue:work --once
```

Pour voir les jobs échoués :
```bash
php artisan queue:failed
```

