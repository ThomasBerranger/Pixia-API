# CLAUDE.md — Pixia API

## 1. Présentation du projet

- **Nom** : Pixia — [pixia.fun](https://pixia.fun)
- **Statut** : Production
- **Concept** : Chaque jour, une image générée par IA est publiée sur Pixia. N'importe qui peut tenter de deviner les œuvres sources (aujourd'hui : 2 films mélangés — d'autres types de contenus sont envisagés).
- **Audience** : Principalement française, 15–55 ans
- **Acquisition** : Réseaux sociaux (Instagram, Facebook, TikTok) — canal principal de rappel quotidien et d'acquisition

---

## 2. Stack technique

- PHP 8.4 + Symfony 7.3
- Doctrine ORM 3 + MySQL
- API Platform 4.2 (utilisé partiellement)
- Lexik JWT Authentication
- Brevo Mailer
- PHPUnit 12 + dama/doctrine-test-bundle
- PHPStan level max + PHP-CS-Fixer
- Hébergement : Railway

---

## 3. Concept & règles du jeu

- **Type de jeu actuel** : MULTIVERSE (seul type disponible) — fusion de 2 films en une image IA
- **Rythme** : 1 Pixia publié par jour
- **Accès** : connecté ou non connecté (la partie est jouable anonymement)

### Réponse
- Nombre de tentatives : illimité
- Fuzzy match : normalisation accents, ordre des mots, tolérance typos, FR/EN
- Définition exacte d'une bonne réponse : les 2 films identifiés (ordre indifférent)
- **Partie terminée dès que la bonne réponse est trouvée — non rejouable**

### Hint
- **Réservé aux utilisateurs connectés**
- Contenu : phrase mystérieuse faisant allusion aux 2 films sans les nommer explicitement
- Impact sur les points : **environ divisé par 2**
- **1 hint max par partie**

---

## 4. Acteurs & permissions

| Rôle | Description |
|------|-------------|
| **Anonyme** | Joue au Pixia du jour, pas de hint, pas de tokens |
| **USER** | Joue + hint + tokens + création de contenu |
| **ADMIN** | Supervise et modère les Pixia soumis |
| **SUPER_ADMIN** | Bypass de certaines règles, gestion globale |
| **BOT** | Utilisateurs fictifs qui jouent automatiquement pour animer les leaderboards. Taux de réussite variable selon la difficulté. N'héritent pas de ROLE_USER |
| **BANNED** | Accès révoqué, n'hérite pas de ROLE_USER |

### Héritage des rôles
- ADMIN et SUPER_ADMIN héritent de ROLE_USER
- BOT et BANNED n'héritent pas de ROLE_USER

---

## 5. Cycle de vie d'un Game

### États
| Statut | Description |
|--------|-------------|
| **DRAFT** | Brouillon en cours de création |
| **PENDING** | Soumis, en attente de modération |
| **APPROVED** | Validé, publié ou planifié |
| **REJECTED** | Refusé |

### Transitions & acteurs

| Transition | Qui |
|------------|-----|
| Création → DRAFT | USER (avec tokens) ou ADMIN |
| DRAFT → PENDING | Auteur uniquement |
| PENDING → APPROVED | ADMIN/SUPER_ADMIN (supervisor du game) |
| PENDING → REJECTED | ADMIN/SUPER_ADMIN (supervisor du game) |
| Toute transition | SUPER_ADMIN (bypass) |

### Règles
- Un ADMIN devient automatiquement supervisor à la création de son propre game
- Un ADMIN doit réclamer un game PENDING pour en devenir supervisor avant de pouvoir l'approuver ou le rejeter
- Le supervisor ne peut pas éditer un game en DRAFT (réservé à l'auteur) ni en APPROVED
- Date assignée automatiquement à la création : aujourd'hui + 2 semaines
- Date modifiable par le supervisor : minimum aujourd'hui + 1 semaine
- Date modifiable par le SUPER_ADMIN : sans contrainte de délai

---

## 6. Système de tokens

### Coûts de création
| Action | Coût |
|--------|------|
| Analyse d'idée | 300 |
| Génération d'image IA | 500 |
| Upload d'image | 200 |
| Soumission | 100 |

- Minimum **1000 tokens** requis pour lancer une création
- Les tokens sont déduits **au moment de l'action** (pas à la validation)
- Les tokens ne peuvent jamais être négatifs

### Remboursements
- **150 tokens** remboursés si la notoriété combinée des films est insuffisante (< 50)
- **150 tokens** remboursés si la génération IA échoue

### Gains
- Jouer : tokens gagnés = points marqués sur la partie
- **Coffre joueur** : réclamable tous les 5 jours, montant aléatoire par paliers
  - 50% de chance → max 40
  - 30% de chance → max 80
  - 15% de chance → max 120
  - 5% de chance → max 180
  - Montant toujours arrondi au multiple de 5
- **Coffre admin** : 995 tokens, réclamable une fois par mois

---

## 7. Système de scoring

| Difficulté | Sans hint | Avec hint |
|------------|-----------|-----------|
| SIMPLE | 10 | 5 |
| VICIEUX | 15 | 10 |
| TORDU | 25 | 15 |
| DIABOLIQUE | 50 | 25 |
| IMPOSSIBLE | 80 | 50 |

- Une fois la bonne réponse trouvée, la partie est terminée et ne peut plus être rejouée
- Les points contribuent au classement global et au classement mensuel
- Les points gagnés = tokens gagnés

---

## 8. Règles métier critiques

- **Unicité** : une seule Game APPROVED par (date, type)
- **Images** : jamais exposées hors statut APPROVED
- **Tokens** : jamais négatifs
- **Notoriété minimale combinée** : 50
- **Notoriété** : calculée par Claude API — ancres : Titanic ~95, Inception ~76, Whiplash ~48, The Lobster ~25, A Ghost Story ~8
- **Date minimum** : aujourd'hui + 1 semaine pour la planification (bypass SUPER_ADMIN)
- **Supervisor** : auto-assigné à la création si l'auteur est admin
- **Données sensibles** : email utilisateur, solution et hint d'un game — exposés uniquement si le contexte l'exige (ex : admin, auteur)

---

## 9. Architecture backend

### Structure des dossiers
```
src/
├── Attribute/        # Attributs custom PHP 8 (ex : RateLimit)
├── Command/          # Commandes console Symfony
├── Controller/       # Controllers REST, organisés par domaine
├── DTO/              # Validation des requêtes entrantes
├── Entity/           # Entités Doctrine
├── Enum/             # Backed enums PHP 8.1+
├── EventSubscriber/  # Subscribers (ex : RateLimitSubscriber)
├── Repository/       # Requêtes Doctrine custom
├── Security/Voter/   # Voters (GameVoter, UserVoter)
├── Serializer/       # Normalizers custom
└── Service/          # Services métier
```

### Principes fondamentaux

**Séparation des responsabilités — strict**
- Controllers : routing + délégation uniquement, jamais de logique métier
- Services : une responsabilité par service, nommage explicite
- Repositories : toutes les requêtes Doctrine, jamais dans les controllers ou services
- DTOs : validation des entrées + conversion vers l'entité (`toEntity()` / `updateGame()`)

**Immuabilité et typage**
- `declare(strict_types=1)` dans chaque fichier
- Services en `readonly` dès que possible
- Injection via constructor promotion `private readonly`
- Typage retour et paramètres obligatoire partout, pas de `mixed` sauf exception justifiée
- Entités avec valeurs par défaut sur les propriétés (pas dans le constructeur)

**Patterns appliqués**
- **Voter** : toute décision d'autorisation passe par `GameVoter` ou `UserVoter` — jamais de logique de permissions dans les controllers ou services
- **Normalizer** : `GameNormalizer` filtre les données sensibles selon le contexte de sérialisation — pattern `ALREADY_CALLED` pour éviter la récursion
- **Listener Doctrine** : `GameListener` pour les behaviors automatiques (supervisor, date, validations)
- **Subscriber** : `RateLimitSubscriber` pour le rate limiting via l'attribut custom `#[RateLimit]`
- **Enum** : tous les statuts, rôles, difficultés, coûts sont des backed enums — jamais de magic strings
- **UUID** : identifiants des entités, jamais d'auto-increment exposé

**Groupes de sérialisation**
- Convention : `entity:action` ou `entity:action:role`
- Exemples : `game:show`, `game:index:admin`, `user:show`
- Les données sensibles (solution, hint, image) sont filtrées par `GameNormalizer` selon le groupe et le voter

**Qualité**
- PHPStan level max
- PHP-CS-Fixer configuré
- Pas de suppression PHPStan sans justification commentée

---

## 10. Conventions de code

### Règles universelles
- `declare(strict_types=1)` en tête de chaque fichier PHP
- Typage retour et paramètres obligatoire partout — pas de `mixed` sauf exception justifiée et commentée

### Controllers
- `final class`, étendent `AbstractController`
- Injection via constructor promotion `private readonly`
- Zéro logique métier — routing + délégation uniquement
- Zéro requête Doctrine — passer par Repository ou Service
- `#[IsGranted]` avec les constantes des Voters (`GameVoter::EDIT`)
- `#[RateLimit]` sur tous les endpoints sensibles

### Services
- `readonly` si possible
- Une responsabilité = un service, nommage explicite
- Jamais d'accès direct à la Request — passer les données via paramètres

### DTOs
- Validation via contraintes Symfony (`#[Assert\*]`)
- Méthode de conversion vers l'entité (`toEntity()` ou `updateGame()`)
- Utilisés avec `#[MapRequestPayload]` ou `#[MapQueryString]`

### Entités
- UUID comme identifiant — jamais d'auto-increment exposé
- Valeurs par défaut sur les propriétés — pas dans le constructeur
- Groupes de sérialisation `#[Groups]` — convention `entity:action` ou `entity:action:role`
- Pas de logique métier dans les entités sauf invariants simples (`removeTokens`, `isBanned`)

### Enums
- Backed enums PHP 8.1+ pour tous les statuts, rôles, difficultés, coûts
- Jamais de magic strings — toujours référencer l'enum

### Repositories
- Méthodes nommées explicitement (`findGameWithUserAchievement`)
- Pas de DQL/SQL inline dans les services ou controllers

### Nommage
- `PascalCase` : classes, enums, DTOs
- `camelCase` : méthodes, propriétés
- `snake_case` : tables et colonnes Doctrine
- Routes : `domain_action` (`game_answer`, `me_tokens_claim`)

---

## 11. Sécurité & permissions

### GameVoter — matrice complète

| Attribut | Condition |
|----------|-----------|
| `VIEW` | SUPER_ADMIN toujours · DRAFT → auteur uniquement · PENDING/REJECTED → auteur ou supervisor |
| `EDIT` | SUPER_ADMIN toujours · DRAFT → auteur · PENDING → tout admin · APPROVED → personne sauf SUPER_ADMIN |
| `SUBMIT` | SUPER_ADMIN toujours · DRAFT → auteur uniquement |
| `SUPERVISE` | SUPER_ADMIN toujours · PENDING sans supervisor → tout admin |
| `APPROVE_OR_REJECT` | SUPER_ADMIN toujours · PENDING → supervisor du game uniquement |

### UserVoter

| Attribut | Condition |
|----------|-----------|
| `VIEW` | SUPER_ADMIN ou ADMIN toujours · sinon soi-même uniquement |

### Filtrage des données sensibles — GameNormalizer
- `solution` et `hint` : masqués par défaut — exposés uniquement si contexte `SHOW_SOLUTION` / `SHOW_HINT` activé
- `image` : masquée si game non APPROVED — sauf contexte admin avec permission `VIEW`
- Pattern `ALREADY_CALLED` pour éviter la récursion du normalizer

### Filtrage des endpoints publics
- Tous les endpoints publics filtrent par `GameStatus::APPROVED` au niveau repository

### Rate limiting
- Attribut custom `#[RateLimit]` sur les endpoints sensibles
- Limites configurées : `global_api`, `game_answer`, `auth_form`
- Désactivé en environnement `test`

### Authentification
- JWT via Lexik JWT Authentication
- `AuthenticatedUserService::getUserOrThrow()` pour récupérer l'utilisateur courant de façon sûre

---

## 12. Endpoints API

### Public
| Méthode | Route | Description |
|---------|-------|-------------|
| `POST` | `/login` | Authentification (⚠️ rate limited) |
| `POST` | `/register` | Inscription |
| `POST` | `/google-auth` | OAuth Google |
| `POST` | `/forgot-password` | Réinitialisation |
| `POST` | `/reset-password` | Nouveau mot de passe |
| `GET` | `/games/daily` | Pixia du jour |
| `GET` | `/games` | Liste des games |
| `POST` | `/games/{id}/solution` | Soumettre une réponse (⚠️ rate limited) |
| `GET` | `/users/leaderboard` | Classement global |
| `GET` | `/users/{slug}` | Profil public |

### Joueur authentifié (USER)
| Méthode | Route | Description |
|---------|-------|-------------|
| `GET` | `/me` | Dashboard |
| `GET` | `/me/tokens` | Solde de tokens |
| `POST` | `/me/tokens/claim` | Réclamer le coffre joueur (cooldown 5j) |
| `POST` | `/me/tokens/claim?isAdminClaim=true` | Réclamer le coffre admin (1x/mois, ADMIN) |
| `GET` | `/me/games` | Historique par mois |
| `GET` | `/me/games-stats` | Statistiques |
| `GET` | `/me/games-generated` | Pixia créés |
| `GET` | `/games/{id}/hint` | Demander l'indice |
| `GET` | `/users/monthly-leaderboard` | Classement mensuel |
| `GET` | `/notifications` | Notifications |
| `GET` | `/notifications/has-unread` | Non lues ? |
| `PATCH` | `/users` | Modifier son profil |
| `POST` | `/contributions` | Soumettre suggestion/bug |

### Création de contenu (USER avec tokens)
| Méthode | Route | Coût | Description |
|---------|-------|------|-------------|
| `POST` | `/me/games` | 300 | Analyser une idée |
| `GET` | `/me/games/{id}` | — | Voir son game |
| `PATCH` | `/me/games/{id}` | 100 si PENDING | Modifier / soumettre |
| `POST` | `/me/games/{id}/image` | 200 | Upload d'image |
| `POST` | `/me/games/{id}/generate-image` | 500 | Générer via IA |
| `DELETE` | `/me/games/{id}/image` | — | Supprimer l'image |

### Admin (ADMIN)
| Méthode | Route | Description |
|---------|-------|-------------|
| `GET` | `/admin/games` | Liste des games |
| `GET` | `/admin/games/taken-dates` | Dates occupées |
| `GET` | `/admin/games/{id}` | Détails d'un game |
| `PATCH` | `/admin/games/{id}` | Modifier statut, date, supervisor |
| `POST` | `/admin/games/{id}/image` | Modifier l'image |

### Super Admin (SUPER_ADMIN)
| Méthode | Route | Description |
|---------|-------|-------------|
| `GET` | `/super-admin/users` | Liste des utilisateurs |
| `PATCH` | `/super-admin/users/{id}` | Modifier un utilisateur |
| `POST` | `/super-admin/impersonate/{id}` | Impersonate |
| `GET` | `/super-admin/games` | Dashboard games |
| `POST` | `/super-admin/games` | Créer un game |
| `GET` | `/super-admin/contributions` | Contributions |
| `PATCH` | `/super-admin/contributions/{id}` | Modérer |

---

## 13. Services métier clés

### Domaine Game
| Service | Responsabilité |
|---------|----------------|
| `GameSolutionService` | Vérification des réponses joueur — fuzzy match FR/EN |
| `GameStatusService` | Validation des transitions de statut |
| `GameDateService` | Validation et génération de dates disponibles |
| `GameCreationService` | Création et validation d'un Game |
| `GameSupervisorService` | Gestion des supervisors |
| `GameImageGenerationService` | Orchestration génération image : analyse → prompt → DALL-E → stockage |
| `GameService` | Logique de jeu (formatting stats, etc.) |

### Domaine User
| Service | Responsabilité |
|---------|----------------|
| `UserService` | Points, badges, stats joueurs |
| `AuthenticatedUserService` | Récupération sécurisée de l'utilisateur courant |
| `TokenService` | Déduction, remboursement et réclamation de tokens |
| `AchievementService` | Enregistrement des victoires et historique de points |
| `MonthlyLeaderboardService` | Calcul et gestion des classements mensuels |

### Domaine API externe
| Service | Responsabilité |
|---------|----------------|
| `ClaudeApiService` | Analyse d'idée film → notoriété + prompt image + hint |
| `ClaudeApiLogger` | Logging structuré des appels Claude |
| `OpenApiService` | Génération d'image via DALL-E |

### Domaine Infrastructure
| Service | Responsabilité |
|---------|----------------|
| `ImageService` | Stockage, déplacement et suppression des images |
| `ActivityService` | Audit trail de toutes les actions significatives |
| `MailService` | Emails transactionnels via Brevo |
| `ContributionService` | Validation des contributions |

### Dépendances critiques
- `GameImageGenerationService` dépend de `ClaudeApiService` et `OpenApiService` — échec → remboursement automatique
- `TokenService` dépend de `ActivityService` pour tracer chaque mouvement de tokens
- `ClaudeApiService` est le point d'entrée unique vers l'API Anthropic

---

## 14. Stratégie de tests

### Principe directeur
Tester ce qui peut **casser silencieusement** en production. Un test qui ne peut pas échouer suite à un vrai bug métier est inutile.

### Les 4 axes de couverture

**Axe 1 — Règles métier** (`tests/Unit/`, `TestCase` pur, mocks)
- Fuzzy match : accents, ordre des mots, typos, FR/EN, chiffres
- Transitions de statut : autorisées par rôle, interdites → exception
- Tokens : jamais négatifs, remboursement, cooldown
- Unicité des dates : une seule Game APPROVED par (date, type)
- Scoring : points par difficulté avec/sans hint, non rejouable
- Permissions : `GameVoter` et `UserVoter` — matrice complète

**Axe 2 — Sérialisation** (`tests/Unit/`, `KernelTestCase`)
- `solution` et `hint` absents par défaut, présents uniquement si contexte autorisé
- `image` absente si game non APPROVED
- `email` jamais exposé dans les réponses publiques
- Cohérence des groupes : si `image` est sérialisé, `type` et `date` doivent l'être aussi

**Axe 3 — Endpoints** (`tests/Integration/`, `WebTestCase`)
- Codes HTTP : 200, 201, 401, 403, 404, 422
- Format des erreurs de validation uniforme (`violations`)
- Endpoints publics accessibles sans token
- Endpoints authentifiés → 401 sans token
- Endpoints avec voter → 403 si permissions insuffisantes
- `POST /games/{id}/solution` : bonne réponse → points crédités
- `GET /games/daily` : image présente si APPROVED, absente sinon
- `PATCH /admin/games/{id}` : transition valide acceptée, invalide rejetée

**Axe 4 — Effets de bord** (`tests/Integration/`, `WebTestCase` + assertions BDD)
- Après bonne réponse : `Achievement` créé, tokens crédités, score mis à jour
- Après déduction tokens : solde correct, `Activity` enregistrée
- Après création admin : supervisor auto-assigné, date à +2 semaines
- Après remboursement : tokens restitués

### Setup technique
- `dama/doctrine-test-bundle` — rollback automatique après chaque test
- Fixtures centralisées dans `tests/Fixtures/` : 3 users + games dans chaque statut
- `ClaudeApiService` et `OpenApiService` toujours mockés — jamais d'appel réel en test

### Règles de contribution
- Tout nouveau **service** → test unitaire obligatoire avant merge
- Tout nouvel **endpoint** → test d'intégration obligatoire avant merge
- Nommage : `testActionConditionExpectedResult`

---

## 15. CI/CD & qualité

### Pipeline GitHub Actions
**Job `tests`** (avec MySQL 8.0)
1. Setup PHP 8.4
2. `composer install`
3. `vendor/bin/phpunit`

**Job `code-quality`**
1. `vendor/bin/php-cs-fixer fix --dry-run --diff`
2. `composer stan` → PHPStan level max

### Bloque le merge
- Tout test PHPUnit en échec
- Toute violation PHP-CS-Fixer
- Toute erreur PHPStan

### Commandes utiles
```bash
composer test   # PHPUnit
composer cs     # PHP-CS-Fixer (applique)
composer stan   # PHPStan
```

### Déploiement
- Automatique sur Railway à chaque push sur `main`

---

## 16. Points à adresser (TODO)

- [ ] Monitoring prod absent — intégrer Sentry
- [ ] Stratégie de cache — leaderboards et game du jour (Redis)

---

## 17. Notes & pièges connus

- **`GameNormalizer` — pattern `ALREADY_CALLED`** : sans ce flag, le normalizer s'appelle récursivement à l'infini. Ne jamais retirer cette ligne.
- **`GameNormalizer` — groupes obligatoires avec `image`** : si `image` est sérialisé, `type` et `date` doivent l'être aussi — sinon `LogicException` au runtime.
- **Unicité de date — PHP only** : validée via `GameDateService`. Pas de contrainte BDD. Plusieurs DRAFT/PENDING sur la même date sont légitimes.
- **`GameListener` — behaviors automatiques** : supervisor auto-assigné et date calculée dans le listener Doctrine — pas dans le controller ni le service.
- **Tokens — jamais négatifs** : `User::removeTokens()` lève une `LogicException`. `TokenService` la catch et relève en `UnprocessableEntityHttpException`. Ne jamais appeler `removeTokens()` directement depuis un controller.

---

## Réponses attendues

- Code concis, typé, conforme aux conventions Symfony 7.3 et PHP 8.4
- Respecter les patterns existants du projet — ne pas réinventer ce qui existe
- Privilégier les attributs PHP 8 aux annotations
- Proposer des optimisations (query, index, cache) si pertinent, sans sur-ingéniérie
- Français pour les commentaires et messages utilisateur
- Pas de dépendance ajoutée sans justification explicite