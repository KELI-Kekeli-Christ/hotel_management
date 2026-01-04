# Guide d'Utilisation - Système de Gestion Hôtelière

## Table des matières

1. [Introduction](#introduction)
2. [Accès au système](#accès-au-système)
3. [Rôles et permissions](#rôles-et-permissions)
4. [Guide Client](#guide-client)
5. [Guide Administrateur](#guide-administrateur)
6. [Réservations](#réservations)
7. [Commandes de Services](#commandes-de-services)
8. [Gestion des Chambres](#gestion-des-chambres)
9. [Paiements avec FedaPay](#paiements-avec-fedapay)
10. [FAQ](#faq)

---

## Introduction

Ce système de gestion hôtelière permet de gérer les réservations de chambres, les commandes de services (restaurant, room service), et les paiements en ligne via FedaPay.

---

## Accès au système

### URL de l'application
- **Frontend** : `http://localhost:3000` (en développement)
- **Backend API** : `http://localhost:8000/api`

### Connexion
1. Accédez à la page de connexion
2. Entrez votre email et mot de passe
3. Cliquez sur "Se connecter"

### Création de compte
Les nouveaux utilisateurs peuvent créer un compte depuis la page d'inscription (si cette fonctionnalité est activée).

---

## Rôles et permissions

Le système gère différents rôles avec des permissions spécifiques :

### **Client**
- Consulter les chambres disponibles
- Réserver une chambre (avec paiement FedaPay)
- Consulter ses réservations
- Modifier ses réservations (avec paiement partiel)
- Commander des services (restaurant)
- Consulter ses commandes
- Recevoir des notifications

### **Administrateur**
- Toutes les permissions du client
- Gérer les utilisateurs
- Gérer les chambres
- Créer et modifier des réservations pour les clients
- Gérer les commandes
- Gérer le menu des services
- Consulter les statistiques
- Gérer les notifications

---

## Guide Client

### Consulter les chambres disponibles

1. Connectez-vous à votre compte
2. Accédez à la section **"Chambres"** dans le menu
3. Les chambres disponibles sont marquées en vert
4. Les chambres réservées sont marquées en rouge (indisponibles)
5. Cliquez sur une chambre pour voir les détails (prix, type, équipements)

### Réserver une chambre

1. Accédez à la section **"Chambres"**
2. Cliquez sur le bouton **"Réserver"** sur une chambre disponible
3. Sélectionnez les dates d'arrivée et de départ
4. Vérifiez le nombre de nuits et le montant total
5. Cliquez sur **"Confirmer la réservation"**
6. Vous serez redirigé vers FedaPay pour effectuer le paiement
7. Complétez le paiement
8. Une fois le paiement validé, la réservation est confirmée avec le statut **"Payée"**

**Note** : La réservation n'est confirmée qu'après validation du paiement via FedaPay.

### Consulter mes réservations

1. Accédez à la section **"Mes Réservations"** dans le menu
2. Vous verrez la liste de toutes vos réservations
3. Chaque réservation affiche :
   - Numéro de réservation
   - Chambre réservée
   - Dates d'arrivée et de départ
   - Nombre de nuits
   - Statut (En attente, Payée, Confirmée, Annulée, etc.)
   - Montant total

### Modifier une réservation

1. Accédez à **"Mes Réservations"**
2. Cliquez sur le bouton **"Modifier"** sur une réservation payée ou confirmée
3. Modifiez les dates d'arrivée et/ou de départ
4. Le système calcule automatiquement :
   - Si la durée reste identique ou diminue : paiement de l'acompte seulement (20% par défaut)
   - Si la durée augmente : paiement de l'acompte + le surplus
5. Cliquez sur **"Enregistrer"**
6. Vous serez redirigé vers FedaPay pour effectuer le paiement supplémentaire
7. Une fois le paiement validé, les nouvelles dates sont appliquées

**Note** : Seules les réservations avec le statut "Payée", "Confirmée" ou "En attente" peuvent être modifiées.

### Commander des services (Restaurant)

1. Accédez à la section **"Services"** dans le menu
2. Parcourez le menu par catégorie
3. Cliquez sur **"Ajouter"** pour ajouter un article à votre commande
4. Ajustez les quantités dans le panier si nécessaire
5. Remplissez le champ **"Localisation"** (ex: Table 5, Chambre 101)
6. Ajoutez une note optionnelle (allergies, instructions spéciales)
7. Cliquez sur **"Passer la commande"**
8. Vous serez redirigé vers FedaPay pour effectuer le paiement
9. Une fois le paiement validé, la commande est automatiquement marquée comme **"Prête"**

### Consulter mes commandes

1. Accédez à la section **"Mes Commandes"** dans le menu
2. Vous verrez la liste de toutes vos commandes
3. Chaque commande affiche :
   - Numéro de commande
   - Type (Restaurant)
   - Localisation
   - Liste des articles commandés avec quantités et prix
   - Note (si ajoutée)
   - Statut (En attente, Prête, Livrée, Annulée)
   - Date de commande
   - Montant total

**Note** : Les commandes sont automatiquement confirmées après validation du paiement via FedaPay.

---

## Guide Administrateur

### Gestion des utilisateurs

1. Accédez à la section **"Utilisateurs"** dans le menu admin
2. Vous pouvez :
   - Voir la liste de tous les utilisateurs
   - Créer un nouvel utilisateur
   - Modifier les informations d'un utilisateur
   - Gérer les permissions et rôles
   - Désactiver un compte

### Gestion des chambres

1. Accédez à la section **"Chambres"** dans le menu admin
2. Vous pouvez :
   - Voir la liste de toutes les chambres
   - Ajouter une nouvelle chambre
   - Modifier une chambre existante (numéro, type, prix, disponibilité)
   - Supprimer une chambre
   - Voir le statut de disponibilité en temps réel

### Gestion des réservations

1. Accédez à la section **"Réservations"** dans le menu admin
2. Vous pouvez :
   - Voir toutes les réservations
   - Créer une réservation pour un client
   - Modifier une réservation
   - Annuler une réservation
   - Voir les détails (client, chambre, dates, montant, statut)

**Note** : Même en tant qu'administrateur, vous devez passer par FedaPay pour créer ou modifier une réservation.

### Gestion des commandes

1. Accédez à la section **"Services"** dans le menu admin
2. Dans l'onglet **"Commandes"**, vous pouvez :
   - Voir toutes les commandes
   - Voir les informations client (nom, email)
   - Voir la note du client (si présente)
   - Voir le statut de chaque commande
   - Les commandes payées sont automatiquement marquées comme **"Prête"**

### Gestion du menu

1. Accédez à la section **"Services"** dans le menu admin
2. Dans l'onglet **"Menu"**, vous pouvez :
   - Voir tous les articles du menu
   - Ajouter un nouvel article (nom, catégorie, prix, description, photo)
   - Modifier un article existant
   - Gérer la disponibilité (en stock / épuisé)
   - Supprimer un article

### Statistiques

1. Accédez au **"Tableau de bord"**
2. Vous verrez :
   - Nombre de réservations en cours
   - Revenus du jour
   - Commandes en attente
   - Statistiques des services

---

## Réservations

### Statuts de réservation

- **En attente** : Réservation créée, en attente de paiement
- **Payée** : Paiement validé via FedaPay
- **Confirmée** : Réservation confirmée (ancien statut)
- **Annulée** : Réservation annulée
- **Échouée** : Échec du paiement
- **Expirée** : Réservation expirée
- **Enregistrée** : Client enregistré (check-in)
- **Départ** : Client parti (check-out)

### Règles de disponibilité

- Une chambre devient **indisponible** dès qu'une réservation est payée
- La chambre redevient **disponible** automatiquement après la date de départ
- Si un paiement est annulé, la chambre redevient disponible immédiatement

### Modification de réservation

**Règles de calcul du paiement supplémentaire :**

- **Pourcentage d'acompte configurable** : 20% par défaut (configurable dans `.env` via `RESERVATION_MODIFICATION_PAYMENT_PERCENTAGE`)

- **Si la durée diminue ou reste identique** :
  - Paiement = Acompte (X% du nouveau montant total)

- **Si la durée augmente** :
  - Paiement = Acompte (X% du nouveau montant) + Surplus (différence entre ancien et nouveau montant)

**Exemple** :
- Réservation initiale : 5 nuits × 10000 XOF = 50000 XOF
- Modification : 7 nuits × 10000 XOF = 70000 XOF
- Acompte (20%) : 70000 × 0.20 = 14000 XOF
- Surplus : 70000 - 50000 = 20000 XOF
- **Montant à payer** : 14000 + 20000 = 34000 XOF

---

## Commandes de Services

### Statuts de commande

- **En attente** : Commande créée, en attente de paiement
- **Prête** : Paiement validé, commande prête (automatique après paiement)
- **Livrée** : Commande livrée au client
- **Annulée** : Commande annulée

### Types de commandes

Actuellement, seul le type **"Restaurant"** est disponible.

### Processus de commande

1. Le client sélectionne les articles dans le menu
2. Remplit la localisation et une note optionnelle
3. Passe la commande
4. Redirection vers FedaPay pour le paiement
5. Après validation du paiement :
   - La commande est automatiquement marquée comme **"Prête"**
   - Les administrateurs reçoivent une notification
   - Le client peut voir sa commande dans "Mes Commandes"

---

## Gestion des Chambres

### Types de chambres

Le système permet de définir différents types de chambres :
- Simple
- Double
- Suite
- etc.

### Disponibilité

- **Disponible** : La chambre peut être réservée
- **Indisponible** : La chambre est actuellement réservée

La disponibilité est gérée automatiquement en fonction des réservations.

---

## Paiements avec FedaPay

### Processus de paiement

1. **Initiation du paiement** :
   - Lors de la création d'une réservation
   - Lors de la modification d'une réservation
   - Lors de la création d'une commande

2. **Redirection vers FedaPay** :
   - Vous êtes redirigé vers la plateforme de paiement FedaPay
   - Choisissez votre opérateur mobile money (MTN, Moov, etc.)
   - Complétez le paiement

3. **Validation** :
   - Après paiement réussi, vous êtes redirigé vers l'application
   - La réservation/commande est automatiquement confirmée
   - Vous recevez une notification de confirmation

4. **En cas d'annulation** :
   - Si vous annulez le paiement, vous êtes redirigé vers l'application
   - La réservation/commande reste en "En attente" ou est annulée
   - La chambre redevient disponible (pour les réservations)

### Opérateurs disponibles

En environnement de test (sandbox), vous verrez "momo test" au lieu des vrais opérateurs.

En production, les opérateurs suivants sont disponibles :
- MTN Mobile Money
- Moov Money
- Autres opérateurs selon la configuration

### Facturation

FedaPay génère automatiquement les factures pour chaque transaction. Le système n'affiche plus les factures internes, car FedaPay gère la facturation.

---

## FAQ

### Puis-je modifier une réservation après paiement ?

Oui, vous pouvez modifier une réservation avec le statut "Payée", "Confirmée" ou "En attente". Un paiement supplémentaire sera requis selon les nouvelles dates.

### Que se passe-t-il si j'annule mon paiement ?

La réservation/commande reste en "En attente" ou est annulée, et la chambre redevient disponible.

### Pourquoi ma commande est automatiquement marquée comme "Prête" après paiement ?

C'est le comportement normal. Une fois le paiement validé, la commande est automatiquement confirmée et marquée comme "Prête" pour que le service puisse la préparer.

### Comment contacter le support ?

Utilisez la section "Contact" du site ou contactez directement l'hôtel.

### Puis-je annuler une réservation après paiement ?

Les annulations doivent être effectuées via l'administration ou en contactant l'hôtel directement. Le remboursement sera traité selon la politique de l'hôtel.


### Comment savoir si ma réservation est confirmée ?

Une fois le paiement validé via FedaPay, la réservation passe au statut "Payée" et la chambre vous est réservée.

---

## Support technique

Pour toute question technique ou problème :
- Vérifiez ce guide d'utilisation
- Consultez le README pour l'installation et la configuration
- Contactez l'équipe de développement

---

**Dernière mise à jour** : Janvier 2026

