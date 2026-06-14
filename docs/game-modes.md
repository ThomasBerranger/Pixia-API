# Nouveaux modes de jeu — spec & implémentation

Statut : **en cours d'ajout**. À ce jour seul **MULTIVERS** est implémenté (`GameType` ne contient que ce case). Ce document spécifie les 3 nouveaux modes et liste le footprint d'implémentation.

## Modes

| Mode | Image | Solution | Nb cibles |
|------|-------|----------|-----------|
| **MULTIVERS** (existant) | 2 films fusionnés en une scène cohérente | les 2 films (ordre indifférent) | 2 films |
| **KAWAII** | version cute / mignonne / Ghibli d'un personnage issu d'un film | **nom du personnage** | 1 personnage |
| **POLYCHROME** | image flat / style logo, très peu d'éléments, couleurs du film + au moins 1 gros clin d'œil | **le film** | 1 film |
| **CONVERGENCE** | **un seul fichier image** splitté en **4 fenêtres** ; les 4 éléments réunis évoquent un film | **le film** | 1 film |

## Décisions actées

- **MULTIVERS = 2 films ; les 3 nouveaux = 1 seule cible** (film ou personnage). Conséquence : plus de notoriété « combinée », solution mono-cible, prompt Claude et DTO d'analyse spécifiques par mode.
- **Rythme** : 1 Pixia publié par jour, tous types confondus. Le type tourne de façon **irrégulière**, choisi manuellement par l'admin (≠ un type fixe par jour de la semaine).
- **Unicité** : devient « **au plus 1 Game APPROVED par date** » (tous types confondus), au lieu de `(date, type)`.
- **Scoring** : grille difficulté/points identique pour tous les modes (cf. [concept-et-modes.md](concept-et-modes.md)).
- **Hint** : fonctionnement identique pour tous les modes.
- **Réponse FR/EN** : `englishSolution` conservé pour tous les modes (utile aussi pour les personnages, ex. « Dark Vador » / « Darth Vader »).
- **Création** : pour l'instant **SUPER_ADMIN uniquement** pour les 3 nouveaux modes. Le flow tokens pixia joueur (analyse / génération / upload / soumission) reste réservé à MULTIVERS.
- **Notoriété** : barème pour les modes 1 film/personnage **non encore défini** — non bloquant tant que c'est super-admin only.
- **Convergence** : reste **un seul fichier image** (splitté en 4 fenêtres), pas 4 fichiers séparés → stockage inchangé.
- **Prompts Claude par mode** : seront fournis au moment de brancher la création sur l'API.

## Footprint d'implémentation

### Couplage MULTIVERS à généraliser
| Élément | État actuel | Action |
|---------|-------------|--------|
| `GameType` enum | un seul case `MULTIVERS` | ajouter `KAWAII`, `POLYCHROME`, `CONVERGENCE` |
| `ClaudeApiService::analyzeMovieIdea()` + prompt système | 100% cinéma / fusion 2 films | prompt système par mode |
| `MovieAnalysisResult` DTO | `films[]` + notoriété combinée | DTO par mode ou abstraction |
| `GameImageGenerationService::resolvePrompt()` | rappelle `analyzeMovieIdea()` | dépend des points ci-dessus |
| Remboursement notoriété < 50 | logique 2 films | conditionner au type / au barème mono-cible |

### Déjà mode-agnostique (rien à toucher)
- `GameSolutionService` — fuzzy match sur la string `solution` / `englishSolution`, quel que soit le contenu.
- Scoring `GameDifficulty::getPoints()` — par difficulté, pas par type.
- `GameNormalizer` — reconstruit le chemin image depuis le `type` porté par le Game.
- `ImageService` — stockage `{base}/{type}/{Y/m}/{fichier}` ; lit toujours `$game->getType()`.

### Migration « 1 APPROVED par date » (unicité + daily)
| Fichier | Changement |
|---------|------------|
| `GameDateService::isDateAvailable()` | retirer le param/filtre `type` → check `(date, APPROVED)` ; ajuster le message d'erreur |
| `GameRepository::getTakenDates()` | ✅ déjà type-agnostique |
| `GameRepository::getApprovedDatesByType()` | code mort (non appelé dans le projet) → suppression |
| `GameRepository::findGameWithUserAchievement()` | retirer `g.type = :type`, le param `?GameType $type`, et `g2.type` / `g3.type` des sous-requêtes prev/next |
| `GameShowRequest` | le paramètre `type` devient inutile pour `daily` |

> **Invariant cible** : au plus 1 Game APPROVED par date, tous types confondus. Le `type` reste une **donnée** du Game (stockage image, chemin, affichage du mode), plus jamais un critère d'unicité ni de recherche du daily.

## Points à clarifier

- Barème de notoriété pour les modes 1 film / 1 personnage.
- Qui/comment fixe la difficulté des nouveaux modes.
- Ouverture éventuelle de la création des nouveaux modes aux USERs (et coûts tokens pixia associés).
