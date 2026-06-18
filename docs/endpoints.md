# Endpoints API

## Public
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

## Joueur authentifié (USER)
| Méthode | Route | Description |
|---------|-------|-------------|
| `GET` | `/me` | Dashboard |
| `GET` | `/me/tokens` | Solde de tokens pixia |
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

## Création de contenu (USER avec tokens pixia)
| Méthode | Route | Coût | Description |
|---------|-------|------|-------------|
| `POST` | `/me/games` | 300 | Analyser une idée |
| `GET` | `/me/games/{id}` | — | Voir son game |
| `PATCH` | `/me/games/{id}` | 100 si PENDING | Modifier / soumettre |
| `POST` | `/me/games/{id}/image` | 200 | Upload d'image |
| `POST` | `/me/games/{id}/generate-image` | 500 | Générer via IA |
| `DELETE` | `/me/games/{id}/image` | — | Supprimer l'image |

## Admin (ADMIN)
| Méthode | Route | Description |
|---------|-------|-------------|
| `GET` | `/admin/games` | Liste des games |
| `GET` | `/admin/games/taken-dates` | Dates occupées |
| `GET` | `/admin/games/{id}` | Détails d'un game |
| `PATCH` | `/admin/games/{id}` | Modifier statut, date, supervisor |
| `POST` | `/admin/games/{id}/image` | Modifier l'image |

## Super Admin (SUPER_ADMIN)
| Méthode | Route | Description |
|---------|-------|-------------|
| `GET` | `/super-admin/users` | Liste des utilisateurs |
| `PATCH` | `/super-admin/users/{id}` | Modifier un utilisateur |
| `POST` | `/super-admin/impersonate/{id}` | Impersonate |
| `GET` | `/super-admin/games` | Dashboard games |
| `POST` | `/super-admin/games` | Créer un game |
| `GET` | `/super-admin/contributions` | Contributions |
| `PATCH` | `/super-admin/contributions/{id}` | Modérer |
