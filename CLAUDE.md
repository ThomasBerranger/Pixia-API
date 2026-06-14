# CLAUDE.md — Pixia API

Point d'entrée du projet. La documentation détaillée est découpée par domaine dans [`docs/`](docs/).

---

## Présentation du projet

- **Nom** : Pixia — [pixia.fun](https://pixia.fun)
- **Statut** : Production
- **Concept** : Chaque jour, une seule image générée par IA est publiée sur Pixia. N'importe qui peut tenter de deviner ce qu'elle cache. Mode actuel : **MULTIVERS** (2 films fusionnés). D'autres modes sont en préparation, cf. [docs/game-modes.md](docs/game-modes.md).
- **Audience** : Principalement française, 15–55 ans
- **Acquisition** : Réseaux sociaux (Instagram, Facebook, TikTok) — canal principal de rappel quotidien et d'acquisition

---

## Stack technique

- PHP 8.4 + Symfony 7.4
- Doctrine ORM 3 + MySQL
- API Platform 4.x (utilisé partiellement)
- Lexik JWT Authentication
- Brevo Mailer
- PHPUnit 12 + dama/doctrine-test-bundle
- PHPStan level max + PHP-CS-Fixer
- Hébergement : Railway

---

## Index de la documentation

| Fichier | Contenu |
|---------|---------|
| [docs/concept-et-modes.md](docs/concept-et-modes.md) | Concept, règles de réponse/hint, tokens pixia, scoring |
| [docs/game-modes.md](docs/game-modes.md) | **Spec détaillée des nouveaux modes** (Kawaii / Polychrome / Convergence) + footprint d'implémentation |
| [docs/cycle-de-vie-game.md](docs/cycle-de-vie-game.md) | États, transitions et règles d'un Game |
| [docs/securite-permissions.md](docs/securite-permissions.md) | Acteurs, rôles, Voters, filtrage des données sensibles, rate limiting |
| [docs/architecture-conventions.md](docs/architecture-conventions.md) | Architecture backend, patterns, conventions de code |
| [docs/endpoints.md](docs/endpoints.md) | Référence des endpoints API |
| [docs/services.md](docs/services.md) | Services métier clés et dépendances |
| [docs/tests.md](docs/tests.md) | Stratégie et axes de tests |
| [docs/ci-cd.md](docs/ci-cd.md) | Pipeline CI/CD, qualité, déploiement |
| [docs/monthly-recap-mail.md](docs/monthly-recap-mail.md) | Récap mensuel par mail (cron, idempotence, désinscription) |

---

## Règles métier critiques

- **Unicité** : une seule Game APPROVED par (date, type)
- **Images** : jamais exposées hors statut APPROVED
- **Tokens pixia** : jamais négatifs (monnaie interne du jeu, ≠ JWT / tokens Claude)
- **Notoriété minimale combinée** : 50
- **Notoriété** : calculée par Claude API — ancres : Titanic ~95, Inception ~76, Whiplash ~48, The Lobster ~25, A Ghost Story ~8
- **Date minimum** : aujourd'hui + 1 semaine pour la planification (bypass SUPER_ADMIN)
- **Supervisor** : auto-assigné à la création si l'auteur est admin
- **Données sensibles** : email utilisateur, solution et hint d'un game — exposés uniquement si le contexte l'exige (ex : admin, auteur)

---

## Notes & pièges connus

- **`GameNormalizer` — pattern `ALREADY_CALLED`** : sans ce flag, le normalizer s'appelle récursivement à l'infini. Ne jamais retirer cette ligne.
- **`GameNormalizer` — groupes obligatoires avec `image`** : si `image` est sérialisé, `type` et `date` doivent l'être aussi — sinon `LogicException` au runtime.
- **Stockage image — type-dépendant** : `ImageService::getDirectoryPath()` construit le chemin `{base}/{type}/{Y/m}/{fichier}`. Le `type` lu est toujours celui porté par le Game, jamais celui de la requête.
- **Unicité de date — PHP only** : validée via `GameDateService`. Pas de contrainte BDD. Plusieurs DRAFT/PENDING sur la même date sont légitimes ; seule la présence d'un APPROVED bloque.
- **`GameListener` — behaviors automatiques** : supervisor auto-assigné et date calculée dans le listener Doctrine — pas dans le controller ni le service.
- **Tokens pixia — jamais négatifs** : `User::removeTokens()` lève une `LogicException`. `TokenService` la catch et relève en `UnprocessableEntityHttpException`. Ne jamais appeler `removeTokens()` directement depuis un controller.
- **Récap mensuel par mail** : feature documentée dans [`docs/monthly-recap-mail.md`](docs/monthly-recap-mail.md) (commande cron, idempotence, désinscription signée, piège de précédence `OR` Doctrine à ne pas retirer).

---

## Réponses attendues

- Code concis, typé, conforme aux conventions Symfony 7.4 et PHP 8.4
- Respecter les patterns existants du projet — ne pas réinventer ce qui existe (cf. [docs/architecture-conventions.md](docs/architecture-conventions.md))
- Privilégier les attributs PHP 8 aux annotations
- Proposer des optimisations (query, index, cache) si pertinent, sans sur-ingéniérie
- Français pour les commentaires et messages utilisateur
- Pas de dépendance ajoutée sans justification explicite
