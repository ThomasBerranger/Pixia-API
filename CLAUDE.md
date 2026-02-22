# Pixia — API Symfony

## Projet
API REST pour Pixia, jeu web quotidien de devinettes cinéma. Symfony hébergé sur Railway avec MySQL.

## Stack
- PHP 8.4 + Symfony 7.3
- Doctrine ORM 3 + MySQL
- API Platform 4.2 (utilisé partiellement)
- Lexik JWT Authentication
- Brevo Mailer (Symfony Mailer)
- PHPUnit pour les tests

## Concept de Pixia

### Le jeu
Pixia est un jeu de devinettes cinéma quotidien où les joueurs doivent identifier des films à partir d'images énigmatiques générées par mashup de plusieurs films (ex: "Matrix des Anneaux" = The Matrix + Lord of the Rings).

Chaque jour, un nouveau Pixia (Game) est disponible.

### Les acteurs

#### 1. **Joueurs (USER)**
- Jouent aux Pixia quotidiens via `GET /games/daily`
- Gagnent des **points (score)** en résolvant les Pixia (10-80 pts selon difficulté)
- Peuvent demander des **hints** (divise les points environ par 2)
- Accumulent des **achievements** (historique de victoires)
- Gagnent des **badges** pour accomplissements spéciaux
- Sont classés dans des **leaderboards** (global + mensuel)
- Possèdent des **tokens** pour créer du contenu généré par IA sur Pixia
- Peuvent parrainer d'autres joueurs (**sponsor/referees**)

#### 2. **Créateurs de contenu (USER avec tokens)**
Les joueurs peuvent créer leurs propres Pixia via un workflow en plusieurs étapes :
1. **Analyse d'idée** (300 tokens) : vérifier la popularité des films via IA
2. **Génération d'image** (500 tokens) : générer l'image via IA (OpenAI)
3. **Upload d'image** (200 tokens) : alternative à la génération (upload manuel)
4. **Soumission** (100 tokens) : soumettre le Pixia pour modération

#### 3. **Administrateurs (ADMIN)**
- Créent et supervisent des Pixia
- Sont automatiquement **supervisor** de leurs propres créations
- Peuvent modifier les Pixia qu'ils supervisent (date, status, image, etc.)
- Approuvent ou rejettent les Pixia soumis (PENDING → APPROVED/REJECTED)
- Ont accès aux endpoints `/admin/*`

#### 4. **Super Administrateurs (SUPER_ADMIN)**
- Privilèges étendus : peuvent modifier n'importe quel Pixia
- Bypass certaines règles (ex: contrainte de date minimum)
- Gèrent les utilisateurs et la modération globale

### Cycle de vie d'un Game (Pixia)

#### États (GameStatus)
1. **DRAFT** : Brouillon en cours de création
2. **PENDING** : Soumis, en attente de validation admin
3. **APPROVED** : Validé, publié ou planifié pour publication
4. **REJECTED** : Refusé par les admins

#### Création et planification
- Nouveau Game créé → statut **DRAFT** par défaut
- Date assignée automatiquement : `aujourd'hui + 2 semaines`
- Si l'auteur est admin/super-admin → devient automatiquement **supervisor**
- La date peut être modifiée :
  - **Super-admin** : n'importe quelle date disponible
  - **Supervisor** : minimum `aujourd'hui + 1 semaine`
- Objectif une seule Game par date et par type et status = approved (unicité enforced)

#### Validation et publication
- Admin/super-admin approuve : **PENDING** → **APPROVED**
- Validation via `GameStatusService` (transitions autorisées, permissions)
- Validation de date via `GameDateService` (disponibilité, contraintes de délai)
- **IMPORTANT** : Seuls les Games **APPROVED** exposent leur image publiquement
  - Tous les endpoints publics filtrent par `GameStatus::APPROVED`
  - Empêche les fuites d'images avant publication

#### Modification des données sensibles
- Les champs **solution**, **hint**, **image** sont filtrés par `GameNormalizer`
- Deux modes de filtrage :
  1. **Gameplay** : Solution/hint cachés sauf si flags `SHOW_SOLUTION`/`SHOW_HINT` activés
  2. **Admin** : Tout visible si permission `VIEW` accordée par le `GameVoter`

### Système de points et récompenses

#### Points (score)
Calculés via `GameDifficulty::getPoints(bool $hinted)` :
- **Sans hint** : SIMPLE=10, VICIEUX=15, TORDU=25, DIABOLIQUE=50, IMPOSSIBLE=80
- **Avec hint** : SIMPLE=5, VICIEUX=10, TORDU=15, DIABOLIQUE=25, IMPOSSIBLE=40
- Ajoutés via `UserService::addGamePoints(User $player, Game $game)`
- Contribuent au classement global et mensuel

#### Tokens
- Champ `tokens` dans `User`, valeur par défaut 0
- Validation dans `User::setTokens()` : impossible d'avoir des tokens négatifs (lance `LogicException`)
- Coûts définis dans `TokenCost` enum
- Récompenses définies dans `TokenReward` enum (à implémenter)
- Exposés via `GET /me/tokens` et dans les réponses user

#### Badges
- Entité `Badge` avec `BadgeName` enum
- Exemples : SPONSOR (parrainer 1 joueur), SPONSOR_5 (parrainer 5 joueurs)
- Attachés au joueur, affichés dans son profil

#### Achievements
- Entité `Achievement` : enregistre chaque victoire (User + Game + date)
- Historique complet des Pixia résolus par un joueur
- Utilisé pour calculs de stats, streaks, etc.

### Endpoints principaux

#### Joueurs (`/games`, `/me`)
- `GET /games/daily` : Pixia du jour (ou date spécifique)
- `POST /games/{id}/answer` : Soumettre une réponse
- `POST /games/{id}/hint` : Demander un indice
- `GET /me` : Dashboard utilisateur (rank, tokens, stats, history)
- `GET /me/games` : Historique des parties jouées par mois
- `GET /me/tokens` : Solde de tokens actuel
- `GET /me/games-generated` : Liste des Pixia créés par l'utilisateur

#### Création de contenu (`/me/games`)
- `POST /me/games` : Créer un Game (analyse d'idée, coût: 300 tokens)
- `PATCH /me/games/{id}` : Modifier son Game en DRAFT
- `POST /me/games/{id}/image` : Upload d'image (coût: 200 tokens)
- `DELETE /me/games/{id}/image` : Supprimer l'image
- `GET /me/games/{id}` : Voir son Game en détail

#### Administration (`/admin/games`)
- `GET /admin/games` : Liste des Games (filtres : mois, statut)
- `GET /admin/games/taken-dates` : Dates déjà occupées (pour planification)
- `POST /admin/games` : Créer un Game admin (coût: 300 tokens)
- `GET /admin/games/{id}` : Détails d'un Game
- `PATCH /admin/games/{id}` : Modifier (status, date, assignToMe, etc.)
- `POST /admin/games/{id}/image` : Upload/modifier l'image
- `GET /admin/games/solution-exist` : Vérifier si solution existe déjà

### Services métier clés

- **GameService** : Logique de jeu (vérification réponses, etc.)
- **GameStatusService** : Validation des transitions de statut
- **GameDateService** : Validation et génération de dates disponibles
- **GameCreationService** : Création et validation de Games
- **GameSupervisorService** : Gestion des supervisors
- **UserService** : Gestion des points, badges, stats joueurs
- **AuthenticatedUserService** : Récupération de l'utilisateur courant
- **ImageService** : Stockage/suppression/déplacement d'images
- **MonthlyLeaderboardService** : Classements mensuels

### Sécurité et permissions

#### GameVoter
Gère les permissions sur les Games :
- **VIEW** : Voir un Game (admin ou author/supervisor)
- **EDIT** : Modifier un Game (super-admin ou supervisor)

#### Filtrage automatique
- **GameNormalizer** : Filtre solution/hint/image selon contexte
- **Repository filters** : Tous les endpoints publics filtrent par `GameStatus::APPROVED`
- **Token validation** : `User::setTokens()` empêche les tokens négatifs

#### Rate limiting
- Attribut `#[RateLimit]` sur endpoints sensibles (soumission de réponses, etc.)

### Notes importantes

- **Images sécurisées** : Seuls les Games APPROVED exposent leurs images aux joueurs
- **Dates uniques** : Objectif une seule Game APPROVED par (date, type). Validation en PHP via `GameDateService`, pas de contrainte BDD (permet plusieurs DRAFT/PENDING sur même date)
- **Listeners Doctrine** : `GameListener` gère les behaviors automatiques (supervisor assignment, date auto, validations)
- **Pas de logique métier dans controllers** : Toujours déléguer aux Services et autres fichiers adaptés

## Architecture & Conventions

### Structure
```
src/
├── Attribute/       # Attributs custom (RateLimit...)
├── Controller/      # Controllers REST organisés par domaine
├── DTO/             # Data Transfer Objects (request/response)
├── Entity/          # Entités Doctrine
├── Enum/            # Enums PHP 8.1+ (UserRole, GameDifficulty, ContributionStatus...)
├── Repository/      # Repositories Doctrine custom
├── Serializer/      # Normalizers custom
├── Service/         # Services métier (une responsabilité par service)
```

### Règles de code
- **`declare(strict_types=1)`** en tête de chaque fichier PHP.
- **Controllers** : `final class`, étendent `AbstractController`. Injection via constructor promotion `private readonly`. Logique métier déléguée aux Services.
- **DTOs** : Utilisés pour la validation des requêtes (`#[MapRequestPayload]`, `#[MapQueryString]`). Méthode `toEntity()` dans le DTO pour la conversion.
- **Entities** : Attributs Doctrine ORM, groupes de sérialisation (`#[Groups]`), UUID comme identifiant (`UuidGenerator`).
- **Enums** : PHP natifs (backed enums). Utilisés pour les rôles, statuts, difficultés.
- **Services** : `readonly` si possible. Une responsabilité = un service. Nommage explicite (`GameSolutionService`, `AuthenticatedUserService`).
- **Repositories** : Méthodes custom nommées explicitement (`findGameWithUserAchievement`, `getPlayerStatsByMonthAndDifficulty`).
- **Routes** : Attributs PHP 8 (`#[Route]`), format JSON explicite, nommage `domain_action`. Architecture RESTful autant que possible (ressources + actions groupées : `/me/tokens/claim` plutôt que `/me/claim-tokens`).
- **Sécurité** : `#[IsGranted]` avec les enums `UserRole`. `AuthenticatedUserService` pour récupérer l'utilisateur courant.
- **Rate Limiting** : Attribut custom `#[RateLimit]` sur les endpoints sensibles.

### Conventions de nommage
- PascalCase : classes, enums, DTOs
- camelCase : méthodes, propriétés
- snake_case : noms de tables/colonnes Doctrine
- Groupes de sérialisation : `entity:action` ou `entity:action:role` (ex: `game:show`, `user:index:admin`)

### Tests
- PHPUnit. Tests unitaires dans `tests/Unit/`, organisés par Service.
- Mocks via `createMock()`. Setup dans `setUp()`.
- Nommage : `testActionConditionExpectedResult` (ex: `testValidateDoesNotThrowWhenNoPendingContribution`).

### Qualité
- Pas de logique métier dans les controllers.
- Pas de requêtes Doctrine dans les controllers (passer par Repository ou Service).
- Pas de `mixed` sauf cas exceptionnel justifié.
- Typage retour et paramètres obligatoire partout.
- Pas de dépendance ajoutée sans justification.

### Réponses attendues
- Code concis, typé, conforme aux conventions Symfony 7.3 et PHP 8.4.
- Respecter les patterns existants du projet.
- Privilégier les attributs PHP 8 aux annotations.
- Proposer des optimisations (query, index, cache) si pertinent.
- Français pour les commentaires et messages utilisateur.
