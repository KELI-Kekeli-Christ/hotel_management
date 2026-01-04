#!/bin/bash

# Script pour démarrer PHP artisan serve (port 8000) et npm run dev en parallèle

# Couleurs pour les messages
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Démarrage des serveurs de développement ===${NC}\n"

# Variables pour stocker les PIDs
PHP_PID=""
NPM_PID=""

# Fonction pour nettoyer les processus à l'arrêt
cleanup() {
    echo -e "\n${YELLOW}Arrêt des serveurs...${NC}"
    [ ! -z "$PHP_PID" ] && kill $PHP_PID 2>/dev/null
    [ ! -z "$NPM_PID" ] && kill $NPM_PID 2>/dev/null
    rm -f /tmp/php-server-$$.log /tmp/npm-server-$$.log 2>/dev/null
    exit 0
}

# Capturer Ctrl+C pour arrêter proprement les serveurs
trap cleanup SIGINT SIGTERM

# Étape 1: Libérer le port 8000 si nécessaire
echo -e "${YELLOW}Vérification du port 8000...${NC}"

# Essayer différentes méthodes pour trouver le processus utilisant le port 8000
PORT_8000_PID=$(lsof -ti:8000 2>/dev/null)

if [ -z "$PORT_8000_PID" ]; then
    # Essayer avec fuser si lsof n'est pas disponible
    PORT_8000_PID=$(fuser 8000/tcp 2>/dev/null | awk '{print $1}')
fi

if [ ! -z "$PORT_8000_PID" ]; then
    echo -e "${YELLOW}Le port 8000 est utilisé par le processus $PORT_8000_PID. Arrêt...${NC}"
    kill -9 $PORT_8000_PID 2>/dev/null
    sleep 1
    echo -e "${GREEN}Port 8000 libéré.${NC}\n"
else
    echo -e "${GREEN}Port 8000 disponible.${NC}\n"
fi

# Étape 2: Démarrer PHP artisan serve sur le port 8000
echo -e "${GREEN}Démarrage de PHP artisan serve sur le port 8000...${NC}"
cd /home/christ/hotel/projet-hotel-back || { echo -e "${RED}Erreur: Impossible d'accéder au répertoire backend${NC}"; exit 1; }

php artisan serve --port=8000 --host=127.0.0.1 &
PHP_PID=$!

# Attendre un peu pour vérifier que PHP démarre correctement
sleep 2

if ps -p $PHP_PID > /dev/null 2>&1; then
    echo -e "${GREEN}✓ PHP artisan serve démarré (PID: $PHP_PID)${NC}"
    echo -e "${GREEN}  → http://127.0.0.1:8000${NC}\n"
else
    echo -e "${RED}✗ Erreur lors du démarrage de PHP artisan serve${NC}"
    exit 1
fi

# Étape 3: Démarrer npm run dev dans le frontend
echo -e "${GREEN}Démarrage de npm run dev...${NC}"
cd /home/christ/hotel/projet-hotel-front || { echo -e "${RED}Erreur: Impossible d'accéder au répertoire frontend${NC}"; kill $PHP_PID 2>/dev/null; exit 1; }

npm run dev &
NPM_PID=$!

# Attendre un peu pour vérifier que npm démarre correctement
sleep 3

if ps -p $NPM_PID > /dev/null 2>&1; then
    echo -e "${GREEN}✓ npm run dev démarré (PID: $NPM_PID)${NC}\n"
else
    echo -e "${RED}✗ Erreur lors du démarrage de npm run dev${NC}"
    kill $PHP_PID 2>/dev/null
    exit 1
fi

# Afficher les informations de démarrage
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}=== Serveurs démarrés avec succès ===${NC}"
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}PHP Backend:  http://127.0.0.1:8000${NC}"
echo -e "${GREEN}NPM Frontend: En cours de démarrage...${NC}"
echo -e "${YELLOW}Appuyez sur Ctrl+C pour arrêter les serveurs${NC}\n"

# Attendre que les processus se terminent
wait $PHP_PID $NPM_PID 2>/dev/null

# Nettoyer
cleanup
