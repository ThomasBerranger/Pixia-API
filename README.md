# Pixia API

> Chaque jour, une image générée par IA cache **deux films mélangés**. À toi de les retrouver.

🎮 **Jouer : [pixia.fun](https://pixia.fun)** · 🖥️ **Application web : [Pixia](https://github.com/ThomasBerranger/Pixia)** (captures et démo)

Ce dépôt est l'**API** de Pixia : jeu quotidien, comptes, jetons, scoring, classements, badges, modération et génération de contenu par IA.

## Le concept

Pixia publie **une image par jour**, jouable par tous, connecté ou non. Dans le mode actuel, **Multivers**, l'image fusionne deux films en une seule scène : il faut identifier les deux titres, dans n'importe quel ordre.

- **Tentatives illimitées**, avec une reconnaissance tolérante : accents, ordre des mots, fautes de frappe, titres FR ou EN.
- **Un indice** par partie pour les joueurs connectés, au prix d'environ la moitié des points.
- **Des points selon la difficulté**, convertis en jetons, avec un classement mensuel et des badges.
- **Les joueurs créent aussi des parties** : l'IA analyse l'idée et génère l'image, puis les admins modèrent avant publication.

D'autres modes sont en préparation : Kawaii, Polychrome et Convergence.

## Stack

- **PHP 8.4** et **Symfony 7.4**
- **Doctrine ORM 3** et **MySQL**
- **API Platform 4**, utilisé pour une partie des ressources
- **Lexik JWT** pour l'authentification, avec connexion Google OAuth
- **Brevo** pour les mails (récapitulatif mensuel, notifications)
- **Claude** (analyse des idées, notoriété, indices) et **OpenAI** (génération d'images)
- **PHPUnit 12**, **PHPStan niveau max** et **PHP-CS-Fixer**
- Déployé sur **Railway**, CI sur **GitHub Actions**

## Points d'architecture

- **Machine à états d'une partie** : `DRAFT` → `PENDING` → `APPROVED` ou `REJECTED`, avec des transitions réservées à l'auteur ou à l'admin superviseur.
- **Rôles et Voters** : anonyme, joueur, admin, super-admin, bot et banni. Les données sensibles (solution, indice, email) ne sont exposées que si le contexte l'exige.
- **Économie de jetons** : le solde ne peut jamais être négatif, et chaque action de création a son coût.
- **Un seul Pixia publié par jour**, vérifié côté PHP par `GameDateService` à chaque passage en `APPROVED`.
- **Rate limiting** par attribut `#[RateLimit]` sur les endpoints sensibles (connexion, réponses).

## Documentation

| Sujet | Fichier |
|---|---|
| Concept, scoring, jetons | [concept-et-modes.md](docs/concept-et-modes.md) |
| Prochains modes de jeu | [game-modes.md](docs/game-modes.md) |
| Cycle de vie d'une partie | [cycle-de-vie-game.md](docs/cycle-de-vie-game.md) |
| Sécurité et permissions | [securite-permissions.md](docs/securite-permissions.md) |
| Architecture et conventions | [architecture-conventions.md](docs/architecture-conventions.md) |
| Endpoints | [endpoints.md](docs/endpoints.md) |
| Services métier | [services.md](docs/services.md) |
| Tests | [tests.md](docs/tests.md) |
| CI/CD | [ci-cd.md](docs/ci-cd.md) |
| Récapitulatif mensuel par mail | [monthly-recap-mail.md](docs/monthly-recap-mail.md) |

## Lancer en local

Prérequis : PHP 8.4 (extensions `intl`, `gd`, `pdo_mysql`), Composer, Docker, la [Symfony CLI](https://symfony.com/download) et OpenSSL.

```bash
composer install
docker compose up -d mysql
mkdir -p config/jwt
openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:<passphrase> 4096
openssl rsa -pubout -in config/jwt/private.pem -passin pass:<passphrase> -out config/jwt/public.pem
```

Crée un fichier `.env.local` :

```bash
APP_ENV=dev
APP_SECRET=<chaine aleatoire>
DATABASE_URL="mysql://root:root@127.0.0.1:3306/pixia?serverVersion=8.0"
CORS_ALLOW_ORIGIN=*
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<passphrase>
MAILER_DSN=null://null
DEFAULT_URI=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:5173
UPLOAD_IMAGES_DIR=images
CLAUDE_CONSOLE_KEY=<clé API Anthropic, pour l'analyse des idées>
OPENAI_API_KEY=<clé API OpenAI, pour la génération d'images>
```

Puis :

```bash
php bin/console doctrine:migrations:migrate
symfony serve    # http://127.0.0.1:8000, l'API répond sur /api
```

Les clés Claude et OpenAI ne sont nécessaires que pour la création de parties par IA. Le reste de l'API fonctionne sans.

## Qualité

```bash
composer test    # PHPUnit
composer stan    # PHPStan niveau max
composer cs      # PHP-CS-Fixer
```

La CI bloque le merge sur tout test en échec, toute violation de style et toute erreur PHPStan.

## Auteur

Projet conçu et développé par [Thomas Berranger](https://github.com/ThomasBerranger).
