# Architecture backend & conventions de code

## Structure des dossiers
```
src/
├── Attribute/        # Attributs custom PHP 8 (ex : RateLimit)
├── Command/          # Commandes console Symfony
├── Controller/       # Controllers REST, organisés par domaine
├── DTO/              # Validation des requêtes entrantes
├── Entity/           # Entités Doctrine
├── Enum/             # Backed enums PHP 8.1+
├── EventListener/    # Listeners Doctrine (ex : GameListener)
├── EventSubscriber/  # Subscribers (ex : RateLimitSubscriber)
├── Repository/       # Requêtes Doctrine custom
├── Security/Voter/   # Voters (GameVoter, UserVoter)
├── Serializer/       # Normalizers custom
└── Service/          # Services métier
```

## Principes fondamentaux

### Séparation des responsabilités — strict
- Controllers : routing + délégation uniquement, jamais de logique métier
- Services : une responsabilité par service, nommage explicite
- Repositories : toutes les requêtes Doctrine, jamais dans les controllers ou services
- DTOs : validation des entrées + conversion vers l'entité (`toEntity()` / `updateGame()`)

### Immuabilité et typage
- `declare(strict_types=1)` dans chaque fichier
- Services en `readonly` dès que possible
- Injection via constructor promotion `private readonly`
- Typage retour et paramètres obligatoire partout, pas de `mixed` sauf exception justifiée
- Entités avec valeurs par défaut sur les propriétés (pas dans le constructeur)

### Patterns appliqués
- **Voter** : toute décision d'autorisation passe par `GameVoter` ou `UserVoter` — jamais de logique de permissions dans les controllers ou services
- **Normalizer** : `GameNormalizer` filtre les données sensibles selon le contexte de sérialisation — pattern `ALREADY_CALLED` pour éviter la récursion
- **Listener Doctrine** : `GameListener` pour les behaviors automatiques (supervisor, date, validations)
- **Subscriber** : `RateLimitSubscriber` pour le rate limiting via l'attribut custom `#[RateLimit]`
- **Enum** : tous les statuts, rôles, difficultés, coûts sont des backed enums — jamais de magic strings
- **UUID** : identifiants des entités, jamais d'auto-increment exposé

### Groupes de sérialisation
- Convention : `entity:action` ou `entity:action:role`
- Exemples : `game:show`, `game:index:admin`, `user:show`
- Les données sensibles (solution, hint, image) sont filtrées par `GameNormalizer` selon le groupe et le voter

### Qualité
- PHPStan level max
- PHP-CS-Fixer configuré
- Pas de suppression PHPStan sans justification commentée

## Conventions de code

### Règles universelles
- `declare(strict_types=1)` en tête de chaque fichier PHP
- Typage retour et paramètres obligatoire partout — pas de `mixed` sauf exception justifiée et commentée
- **Dates** : ne jamais instancier `new \DateTimeImmutable('now')` directement dans la logique métier. Injecter `Symfony\Component\Clock\ClockInterface` et utiliser `$this->clock->now()` (retourne un `DatePoint`) — testable via `MockClock`. Les dates explicites (ex : `'first day of last month'`) se dérivent de `$this->clock->now()->modify(...)`.

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
