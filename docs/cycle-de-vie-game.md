# Cycle de vie d'un Game

## États
| Statut | Description |
|--------|-------------|
| **DRAFT** | Brouillon en cours de création |
| **PENDING** | Soumis, en attente de modération |
| **APPROVED** | Validé, publié ou planifié |
| **REJECTED** | Refusé |

## Transitions & acteurs

| Transition | Qui |
|------------|-----|
| Création → DRAFT | USER (avec tokens pixia) ou ADMIN |
| DRAFT → PENDING | Auteur uniquement |
| PENDING → APPROVED | ADMIN/SUPER_ADMIN (supervisor du game) |
| PENDING → REJECTED | ADMIN/SUPER_ADMIN (supervisor du game) |
| Toute transition | SUPER_ADMIN (bypass) |

## Règles

- Un ADMIN devient automatiquement supervisor à la création de son propre game
- Un ADMIN doit réclamer un game PENDING pour en devenir supervisor avant de pouvoir l'approuver ou le rejeter
- Le supervisor ne peut pas éditer un game en DRAFT (réservé à l'auteur) ni en APPROVED
- Date assignée automatiquement à la création : aujourd'hui + 2 semaines
- Date modifiable par le supervisor : minimum aujourd'hui + 1 semaine
- Date modifiable par le SUPER_ADMIN : sans contrainte de délai

## Unicité

- Une seule Game APPROVED par date (tous types confondus).
- Validée en PHP via `GameDateService`, pas de contrainte BDD.
- Plusieurs DRAFT/PENDING sur la même date sont légitimes ; seule la présence d'un APPROVED bloque.

## Behaviors automatiques

- `GameListener` (listener Doctrine) gère le supervisor auto-assigné et le calcul de date — jamais dans le controller ni le service.
