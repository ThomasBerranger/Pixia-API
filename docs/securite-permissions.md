# Sécurité & permissions

## Acteurs & rôles

| Rôle | Description |
|------|-------------|
| **Anonyme** | Joue au Pixia du jour, pas de hint, pas de tokens pixia |
| **USER** | Joue + hint + tokens pixia + création de contenu |
| **ADMIN** | Supervise et modère les Pixia soumis |
| **SUPER_ADMIN** | Bypass de certaines règles, gestion globale |
| **BOT** | Utilisateurs fictifs qui jouent automatiquement pour animer les leaderboards. Taux de réussite variable selon la difficulté. N'héritent pas de ROLE_USER |
| **BANNED** | Accès révoqué, n'hérite pas de ROLE_USER |

### Héritage des rôles
- ADMIN et SUPER_ADMIN héritent de ROLE_USER
- BOT et BANNED n'héritent pas de ROLE_USER

## GameVoter — matrice complète

| Attribut | Condition |
|----------|-----------|
| `VIEW` | SUPER_ADMIN toujours · DRAFT → auteur uniquement · PENDING/REJECTED → auteur ou supervisor |
| `EDIT` | SUPER_ADMIN toujours · DRAFT → auteur · PENDING → tout admin · APPROVED → personne sauf SUPER_ADMIN |
| `SUBMIT` | SUPER_ADMIN toujours · DRAFT → auteur uniquement |
| `SUPERVISE` | SUPER_ADMIN toujours · PENDING sans supervisor → tout admin |
| `APPROVE_OR_REJECT` | SUPER_ADMIN toujours · PENDING → supervisor du game uniquement |

## UserVoter

| Attribut | Condition |
|----------|-----------|
| `VIEW` | SUPER_ADMIN ou ADMIN toujours · sinon soi-même uniquement |

## Filtrage des données sensibles — GameNormalizer

- `solution` et `hint` : masqués par défaut — exposés uniquement si contexte `SHOW_SOLUTION` / `SHOW_HINT` activé
- `image` : masquée si game non APPROVED — sauf contexte admin avec permission `VIEW`
- Pattern `ALREADY_CALLED` pour éviter la récursion du normalizer

## Filtrage des endpoints publics

- Tous les endpoints publics filtrent par `GameStatus::APPROVED` au niveau repository

## Rate limiting

- Attribut custom `#[RateLimit]` sur les endpoints sensibles
- Limites configurées : `global_api`, `game_answer`, `auth_form`
- Désactivé en environnement `test`

## Authentification

- JWT via Lexik JWT Authentication
- `AuthenticatedUserService::getUserOrThrow()` pour récupérer l'utilisateur courant de façon sûre
