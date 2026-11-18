# Pixia API

> Chaque jour, une image générée par IA cache un film, un personnage ou deux univers mélangés. À toi de les retrouver.

🖥️ **Application web : [Pixia](https://github.com/ThomasBerranger/Pixia)** (captures et démo)

Ce dépôt est l'**API** de Pixia : jeu quotidien, comptes, jetons, scoring, classements, badges, modération et génération de contenu par IA.

## Le concept

Pixia publie **une image par jour**, jouable par tous, connecté ou non. Le type de jeu varie d'un jour à l'autre :

| Mode | L'image montre | À deviner |
|---|---|---|
| **Multivers** | deux films fusionnés en une seule scène | les deux films |
| **Kawaii** | un personnage redessiné en version kawaii | le personnage |
| **Polychrome** | un film résumé en illustration minimaliste | le film |
| **Convergence** | plusieurs éléments d'un même film réunis | le film |

- **Tentatives illimitées**, avec une reconnaissance tolérante : accents, ordre des mots, fautes de frappe, titres FR ou EN.
- **Un indice** par partie pour les joueurs connectés, au prix d'environ la moitié des points.
- **Des points selon la difficulté**, convertis en jetons, avec un classement mensuel et des badges.
- **Les joueurs créent aussi des parties Multivers** : ils décrivent leur idée, l'IA l'analyse et génère l'image, puis les admins modèrent avant publication.

## Stack

- **PHP 8.4** et **Symfony 7.4**
- **Doctrine ORM 3** et **MySQL**
- **Lexik JWT** pour l'authentification, avec connexion Google OAuth
- **Brevo** pour les mails (récapitulatif mensuel, notifications)
- **Claude** (analyse des idées, notoriété, prompt d'image, indice) et **OpenAI** DALL-E 3 (génération d'images)
- **PHPUnit 12**, **PHPStan niveau max** et **PHP-CS-Fixer**
- Déployé sur **Railway**, CI sur **GitHub Actions**

## Chiffres clés

- Environ 8 000 lignes de PHP dans `src/`
- 45 classes de tests, soit environ 400 cas : règles métier, sérialisation, endpoints et effets de bord en base
- PHPStan niveau max, PHP-CS-Fixer et PHPUnit exécutés par la CI à chaque PR, avec rollback automatique de la base entre les tests (`dama/doctrine-test-bundle`)
- 10 documents d'architecture dans [`docs/`](docs/), pensés pour qu'un nouveau contributeur soit opérationnel rapidement

## Documentation

| Sujet | Fichier |
|---|---|
| Concept, scoring, jetons | [concept-et-modes.md](docs/concept-et-modes.md) |
| Modes de jeu | [game-modes.md](docs/game-modes.md) |
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

Projet conçu et développé par [Thomas Berranger](https://github.com/ThomasBerranger) aka Mirage.
