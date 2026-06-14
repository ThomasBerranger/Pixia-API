# Services métier clés

## Domaine Game (`src/Service/Game/`)
| Service | Responsabilité |
|---------|----------------|
| `GameSolutionService` | Vérification des réponses joueur — fuzzy match FR/EN, attribution points/tokens pixia |
| `GameDateService` | Validation des changements de date / unicité |
| `GameImageGenerationService` | Orchestration génération image : analyse → prompt → DALL-E → stockage |
| `GameService` | Logique de jeu (formatting stats, etc.) |

Les transitions de statut, la création et la gestion des supervisors passent par `GameListener`, les Voters et les controllers (pas de service dédié).

## Domaine User
| Service | Responsabilité |
|---------|----------------|
| `UserService` | Points, badges, stats joueurs |
| `AuthenticatedUserService` | Récupération sécurisée de l'utilisateur courant |
| `TokenService` | Déduction, remboursement et réclamation de tokens pixia |
| `AchievementService` | Enregistrement des victoires et historique de points |
| `BadgeService` | Gestion des badges |
| `MonthlyLeaderboardService` | Calcul et gestion des classements mensuels |
| `MonthlyRecapService` | Récap mensuel par mail (cf. [monthly-recap-mail.md](monthly-recap-mail.md)) |
| `NotificationService` | Notifications joueur |

## Domaine API externe (`src/Service/API/`)
| Service | Responsabilité |
|---------|----------------|
| `ClaudeApiService` | Analyse d'idée film → notoriété + prompt image + hint (point d'entrée unique vers l'API Anthropic) |
| `ClaudeApiLogger` | Logging structuré des appels Claude |
| `OpenApiService` | Génération d'image via DALL-E |
| `GoogleApiService` | OAuth / vérification Google |

## Domaine Infrastructure
| Service | Responsabilité |
|---------|----------------|
| `ImageService` | Stockage, déplacement et suppression des images (`{base}/{type}/{Y/m}/{fichier}`) |
| `ActivityService` | Audit trail de toutes les actions significatives |
| `MailService` | Emails transactionnels via Brevo |
| `ContributionService` | Validation des contributions |

## Dépendances critiques
- `GameImageGenerationService` dépend de `ClaudeApiService` et `OpenApiService` — échec → remboursement automatique
- `TokenService` dépend de `ActivityService` pour tracer chaque mouvement de tokens pixia
- `ClaudeApiService` est le point d'entrée unique vers l'API Anthropic
