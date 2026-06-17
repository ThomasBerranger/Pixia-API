# Stratégie de tests

## Principe directeur
Tester ce qui peut **casser silencieusement** en production. Un test qui ne peut pas échouer suite à un vrai bug métier est inutile.

## Les 4 axes de couverture

### Axe 1 — Règles métier (`tests/Unit/`, `TestCase` pur, mocks)
- Fuzzy match : accents, ordre des mots, typos, FR/EN, chiffres
- Transitions de statut : autorisées par rôle, interdites → exception
- Tokens pixia : jamais négatifs, remboursement, cooldown
- Unicité des dates : une seule Game APPROVED par date (tous types confondus)
- Scoring : points par difficulté avec/sans hint, non rejouable
- Permissions : `GameVoter` et `UserVoter` — matrice complète

### Axe 2 — Sérialisation (`tests/Unit/`, `KernelTestCase`)
- `solution` et `hint` absents par défaut, présents uniquement si contexte autorisé
- `image` absente si game non APPROVED
- `email` jamais exposé dans les réponses publiques
- Cohérence des groupes : si `image` est sérialisé, `type` et `date` doivent l'être aussi

### Axe 3 — Endpoints (`tests/Integration/`, `WebTestCase`)
- Codes HTTP : 200, 201, 401, 403, 404, 422
- Format des erreurs de validation uniforme (`violations`)
- Endpoints publics accessibles sans token
- Endpoints authentifiés → 401 sans token
- Endpoints avec voter → 403 si permissions insuffisantes
- `POST /games/{id}/solution` : bonne réponse → points crédités
- `GET /games/daily` : image présente si APPROVED, absente sinon
- `PATCH /admin/games/{id}` : transition valide acceptée, invalide rejetée

### Axe 4 — Effets de bord (`tests/Integration/`, `WebTestCase` + assertions BDD)
- Après bonne réponse : `Achievement` créé, tokens pixia crédités, score mis à jour
- Après déduction tokens pixia : solde correct, `Activity` enregistrée
- Après création admin : supervisor auto-assigné, date à +2 semaines
- Après remboursement : tokens pixia restitués

## Setup technique
- `dama/doctrine-test-bundle` — rollback automatique après chaque test
- Fixtures centralisées dans `tests/Fixtures/` : 3 users + games dans chaque statut
- `ClaudeApiService` et `OpenApiService` toujours mockés — jamais d'appel réel en test

## Règles de contribution
- Tout nouveau **service** → test unitaire obligatoire avant merge
- Tout nouvel **endpoint** → test d'intégration obligatoire avant merge
- Nommage : `testActionConditionExpectedResult`
