# Pixia API

> Chaque jour, une image générée par IA cache **deux films mélangés**. À toi de les retrouver.

🖥️ **Application web : [Pixia](https://github.com/ThomasBerranger/Pixia)** (captures et démo)

Ce dépôt est l'**API** de Pixia : jeu quotidien, comptes, jetons, scoring, classements, badges, modération et génération de contenu par IA.

## Le concept

Pixia publie **une image par jour**, jouable par tous, connecté ou non. Dans le mode actuel, **Multivers**, l'image fusionne deux films en une seule scène : il faut identifier les deux titres.

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

## Points techniques

**Pipeline de création assisté par IA.** `ClaudeApiService` analyse l'idée du joueur (notoriété des films, prompt d'image, indice), puis `OpenApiService` génère l'image, et `ImageService` la range selon le type et la date. Tout échec de la chaîne rembourse automatiquement les jetons, et chaque mouvement est tracé par `ActivityService` (audit trail).

**Reconnaissance tolérante des réponses.** `GameSolutionService` normalise le texte (Unicode NFD, suppression des accents, tokenisation) et compare les mots avec une distance de Levenshtein : l'ordre des mots, les fautes de frappe et les titres FR ou EN sont acceptés.

**Machine à états et autorisations.** Une partie passe de `DRAFT` à `PENDING`, puis `APPROVED` ou `REJECTED`. Les transitions sont gouvernées par une matrice de Voters (`GameVoter`, `UserVoter`) sur cinq rôles, avec héritage. Les listeners Doctrine (`GameListener`, `BadgeListener`, `AchievementListener`) portent les effets de bord : superviseur auto-assigné, badges, succès.

**Invariants métier garantis dans le code.** Un seul Pixia `APPROVED` par date, vérifié par `GameDateService` à chaque passage en `APPROVED`. Le solde de jetons ne peut jamais être négatif : `User::removeTokens()` lève une exception, convertie en 422 par `TokenService`.

**Sécurité par défaut.** `GameNormalizer` masque la solution, l'indice et l'image tant que le contexte ne l'autorise pas, et les endpoints publics filtrent sur `APPROVED` dès le repository. L'API pose des headers de sécurité (CSP, `X-Frame-Options`, `nosniff`…), applique un rate limiting par attribut `#[RateLimit]` et refuse en `426` les fronts trop anciens (`AppVersionSubscriber`).

**Récapitulatif mensuel par mail.** Une commande cron envoie un récapitulatif personnalisé selon le rang (`RecapTier`). Elle est **idempotente et reprenable** : un re-run n'écrit qu'aux destinataires restants. Le consentement est vérifié, la désinscription se fait en un clic via une URL signée (`UriSigner`), et un test de non-régression protège la précédence des `OR` Doctrine qui aurait sinon écrit à des utilisateurs désinscrits ou bannis.

**Joueurs bots.** Une commande (`app:make-bots-play`) fait jouer des utilisateurs fictifs, avec un taux de réussite qui dépend de la difficulté, pour animer les classements.

**Exploitation.** Un workflow GitHub Actions sauvegarde la base MySQL chaque nuit (rétention de 30 jours) et envoie un mail d'alerte en cas d'échec. Les mails de dev et de test sont verrouillés vers une boîte unique, ce qui rend impossible l'envoi à un vrai joueur hors production.

## Chiffres clés

- Environ 8 000 lignes de PHP dans `src/`
- 45 classes de tests, soit environ 400 cas : règles métier, sérialisation, endpoints et effets de bord en base
- PHPStan niveau max, PHP-CS-Fixer et PHPUnit exécutés par la CI à chaque PR, avec rollback automatique de la base entre les tests (`dama/doctrine-test-bundle`)
- 10 documents d'architecture dans [`docs/`](docs/), pensés pour qu'un nouveau contributeur soit opérationnel rapidement

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

Projet conçu et développé par [Thomas Berranger](https://github.com/ThomasBerranger) aka Mirage.
