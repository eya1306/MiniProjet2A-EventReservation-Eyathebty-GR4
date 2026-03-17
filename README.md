# 🎟️ EventReserve — Application Web de Gestion de Réservations d'Événements

> **Mini Projet FIA3-GL** · ISSAT Sousse · Année universitaire 2025-2026

Application web complète permettant aux utilisateurs de consulter des événements et de réserver en ligne, et à un administrateur de gérer les événements et les réservations via une interface sécurisée.

---

## 📋 Technologies utilisées

| Couche | Technologie |
|--------|-------------|
| **Backend** | Symfony 7 (PHP 8.2+) |
| **Base de données** | PostgreSQL 15 |
| **Authentification** | JWT (LexikJWTAuthenticationBundle) + Passkeys (WebAuthn/FIDO2) |
| **Templating** | Twig 3 |
| **Conteneurisation** | Docker + Docker Compose |
| **Frontend** | HTML5, CSS3 Vanilla, JavaScript ES2022 |
| **ORM** | Doctrine ORM 3 |

---

## 🏗️ Architecture

```
event-reservation/
├── config/               # Configuration Symfony (security, doctrine, JWT...)
├── migrations/           # Migrations SQL Doctrine
├── public/               # Point d'entrée (index.php, CSS, JS, uploads)
│   ├── css/
│   │   ├── app.css       # Interface utilisateur
│   │   └── admin.css     # Interface admin
│   └── js/
│       ├── app.js
│       └── auth.js       # WebAuthn + JWT (Passkeys)
├── src/
│   ├── Controller/       # Contrôleurs utilisateur
│   │   └── Admin/        # Contrôleurs admin
│   ├── Entity/           # Entités Doctrine (User, Admin, Event, Reservation)
│   ├── Repository/       # Repositories
│   ├── Service/          # Services métier
│   └── DataFixtures/     # Données de test
├── templates/            # Templates Twig
│   ├── admin/            # Vue admin
│   ├── events/           # Vue événements
│   ├── reservations/     # Confirmation
│   └── security/         # Login / Register
├── docker/               # Configuration Docker (Nginx)
├── docker-compose.yml
└── Dockerfile
```

---

## ⚙️ Consignes d'installation

### Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop) (v24+) ou Docker + Docker Compose
- Git

### 1. Cloner le dépôt

```bash
git clone https://github.com/VotreEquipe/MiniProjet2A-EventReservation-NomEquipe.git
cd MiniProjet2A-EventReservation-NomEquipe
```

### 2. Générer les clés JWT RSA

```bash
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
chmod 600 config/jwt/private.pem config/jwt/public.pem
```

> Notez la **passphrase** saisie — vous devrez la configurer dans la variable `JWT_PASSPHRASE`.

### 3. Configurer les variables d'environnement

```bash
cp .env .env.local
```

Éditez `.env.local` et ajustez :

```env
JWT_PASSPHRASE=votre_passphrase_jwt
APP_SECRET=une_chaine_aleatoire_longue
```

### 4. Lancer les conteneurs Docker

```bash
docker compose up -d --build
```

Attendre que la base de données soit prête (~15 secondes), puis :

```bash
# Exécuter les migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Charger les données de démo (admin + 3 événements)
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

### 5. Accéder à l'application

| URL | Description |
|-----|-------------|
| http://localhost:8080 | Application utilisateur |
| http://localhost:8080/admin | Interface administrateur |
| http://localhost:8080/events | Liste des événements |

### 6. Identifiants par défaut

| Rôle | Identifiant | Mot de passe |
|------|-------------|--------------|
| Admin | `admin` | `Admin@1234` |

> **Note Passkeys** : Pour tester les Passkeys/WebAuthn, vous devez utiliser HTTPS ou `localhost`. La fonctionnalité est désactivée sur les IPs publiques non sécurisées.

---

## 🔐 Architecture d'authentification (JWT + Passkeys)

### Flux JWT (mot de passe)

```
1. POST /api/login          → {username, password}
2. Réponse                  → {token, refresh_token}
3. Requêtes API             → Authorization: Bearer <token>
4. POST /api/token/refresh  → Renouveler le token
```

### Flux Passkeys (WebAuthn)

```
1. POST /api/auth/register/options  → challenge WebAuthn (création)
2. navigator.credentials.create()  → Biométrie / PIN utilisateur
3. POST /api/auth/register/verify   → Attestation → JWT généré
4. POST /api/auth/login/options     → challenge WebAuthn (assertion)
5. navigator.credentials.get()     → Signature biométrique
6. POST /api/auth/login/verify      → Vérification → JWT généré
```

---

## 🐳 Commandes Docker utiles

```bash
# Démarrer les services
docker compose up -d

# Arrêter les services
docker compose down

# Voir les logs PHP
docker compose logs -f php

# Accès shell PHP
docker compose exec php bash

# Créer une migration
docker compose exec php php bin/console make:migration

# Vider le cache
docker compose exec php php bin/console cache:clear
```

---

## 🧪 Tests

```bash
# Lancer tous les tests PHPUnit
docker compose exec php php bin/phpunit

# Tests d'un contrôleur spécifique
docker compose exec php php bin/phpunit --filter SecurityControllerTest
```

---

## 📁 Branches Git

| Branche | Description |
|---------|-------------|
| `main` | Code stable et validé |
| `dev` | Intégration et tests |
| `feature/auth` | Authentification JWT + Passkeys |
| `feature/events` | Gestion des événements |
| `feature/admin` | Interface administrateur |
| `feature/docker` | Configuration Docker |

---

## 👥 Membres de l'équipe

| Nom | Rôle |
|-----|------|
|eya thebty| Développeur Full-Stack |

---

## 📚 Ressources

- [Documentation Symfony 7](https://symfony.com/doc/7.0/)
- [LexikJWT Bundle](https://github.com/lexik/LexikJWTAuthenticationBundle)
- [WebAuthn Level 2 (W3C)](https://www.w3.org/TR/webauthn-2/)
- [FIDO2 / Passkeys](https://fidoalliance.org/passkeys/)
- [Docker Documentation](https://docs.docker.com/)
