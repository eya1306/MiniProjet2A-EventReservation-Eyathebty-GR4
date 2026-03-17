# Mini Projet : Application Web de Gestion de Réservations d'Événements

**Projet développé dans le cadre du module FIA3-GL**
**Institut Supérieur des Sciences Appliquées et de Technologie de Sousse (ISSAT Sousse)**
**Département Informatique**

---

## 📋 Description du projet
Cette application web complète permet à des utilisateurs de consulter des événements et de réserver leurs places en ligne. Elle intègre également un tableau de bord d'administration pour la gestion des événements et le suivi des réservations.

L'objectif principal de ce projet est de mettre en œuvre des mécanismes de sécurité modernes et renforcés via une architecture **Stateless**. L'authentification est gérée par **Passkeys (WebAuthn)**, offrant une résistance au phishing et une connexion sans mot de passe via la biométrie (ou code PIN de l'appareil), couplée à la génération de **Tokens JWT** (JSON Web Tokens) pour l'autorisation des requêtes.

## 🛠️ Technologies utilisées
- **Backend Plateforme** : PHP 8.2 & Symfony 7
- **Base de données** : PostgreSQL 15 (via Doctrine ORM)
- **Authentification & Sécurité** :
  - **Passkeys (WebAuthn)** : `web-auth/webauthn-lib` & `web-auth/symfony-bundle`
  - **JWT** : `lexik/jwt-authentication-bundle` & `gesdinet/jwt-refresh-token-bundle`
  - Hashage des mots de passe classique : `Argon2id`
- **Frontend** : Twig, Vanilla JS (API `navigator.credentials`), CSS natif
- **Administration** : EasyAdmin Bundle
- **Infrastructure** : Docker & Docker Compose (Nginx, PHP-FPM, PostgreSQL)

---

## 🚀 Consignes d'installation (Docker)

L'application est entièrement Dockerisée pour faciliter son déploiement. Assurez-vous d'avoir Docker et Docker Compose installés sur votre machine.

### 1. Clonage du dépôt
```bash
git clone https://github.com/VOTRE_COMPTE/MiniProjet2A-EventReservation-VOTRE_EQUIPE.git
cd MiniProjet2A-EventReservation-VOTRE_EQUIPE
git checkout dev
```

### 2. Configuration de l'environnement
Copiez le fichier d'exemple et configurez vos variables d'environnement si nécessaire (les valeurs par défaut conviennent au développement local) :
```bash
cp .env .env.local
```

### 3. Génération des clés JWT (Obligatoire)
L'application utilise une paire de clés asymétriques RSA 4096 bits pour signer et vérifier les tokens JWT.
Générez-les avec les commandes suivantes (via Git Bash ou Linux) :
```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:your_jwt_passphrase
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:your_jwt_passphrase
chmod 600 config/jwt/private.pem config/jwt/public.pem
```
*(Assurez-vous que la passphrase utilisée correspond à la variable `JWT_PASSPHRASE` dans votre fichier `.env.local`)*

### 4. Démarrage des conteneurs
```bash
docker compose up -d
```

### 5. Installation des dépendances et Base de données
Une fois les conteneurs lancés, exécutez les commandes suivantes depuis le conteneur PHP :
```bash
# Installation des dépendances vendor
docker compose exec php composer install

# Création de la base de données et exécution des migrations
docker compose exec php php bin/console doctrine:database:create --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate -n

# Chargement du jeu de données initial (Fixtures : Admin, Événements de test)
docker compose exec php php bin/console doctrine:fixtures:load -n
```

### 6. Accès à l'application
- **Espace Public / Utilisateurs** : `http://localhost:8080`
- **Espace Administrateur** : `http://localhost:8080/admin`
  - *Identifiant défaut* : `admin`
  - *Mot de passe défaut* : `admin`

---

## 👥 Identités des membres de l'équipe
- **[Votre Nom & Prénom]** - *Rôle / Contribution*
- *(Ajoutez les autres membres si applicable)*

---

*Projet réalisé pour l'année universitaire 2025/2026 - Rendu fin Février 2026*
