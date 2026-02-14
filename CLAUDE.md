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
- **Routes** : Attributs PHP 8 (`#[Route]`), format JSON explicite, nommage `domain_action`.
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
