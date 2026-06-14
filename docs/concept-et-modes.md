# Concept & règles du jeu

Pixia publie **1 image générée par IA par jour**, jouable par tous (connecté ou non).

## Mode actuel — MULTIVERS

2 films fusionnés en une seule scène cohérente ; le joueur doit deviner les **2 films** (ordre indifférent).

> Les modes à venir (Kawaii, Polychrome, Convergence) sont spécifiés dans [game-modes.md](game-modes.md).

## Réponse

- Nombre de tentatives : illimité
- Fuzzy match : normalisation accents, ordre des mots, tolérance typos, FR/EN (opère sur la string `solution` / `englishSolution`)
- Bonne réponse : les 2 films identifiés (ordre indifférent)
- **Partie terminée dès que la bonne réponse est trouvée — non rejouable**

## Hint

- **Réservé aux utilisateurs connectés**
- Contenu : phrase mystérieuse faisant allusion à la solution sans la nommer explicitement
- Impact sur les points : **environ divisé par 2**
- **1 hint max par partie**

## Système de tokens pixia

Les **tokens pixia** sont la monnaie interne du jeu (à ne pas confondre avec le JWT d'authentification ni les tokens de l'API Claude).

### Coûts de création
| Action | Coût |
|--------|------|
| Analyse d'idée | 300 |
| Génération d'image IA | 500 |
| Upload d'image | 200 |
| Soumission | 100 |

- Minimum **1000 tokens pixia** requis pour lancer une création
- Les tokens pixia sont déduits **au moment de l'action** (pas à la validation)
- Les tokens pixia ne peuvent jamais être négatifs

### Remboursements
- **150 tokens pixia** remboursés si la notoriété combinée des films est insuffisante (< 50)
- **150 tokens pixia** remboursés si la génération IA échoue

### Gains
- Jouer : tokens pixia gagnés = points marqués sur la partie
- **Coffre joueur** : réclamable tous les 5 jours, montant aléatoire par paliers
  - 50% de chance → max 40
  - 30% de chance → max 80
  - 15% de chance → max 120
  - 5% de chance → max 180
  - Montant toujours arrondi au multiple de 5
- **Coffre admin** : 995 tokens pixia, réclamable une fois par mois

## Système de scoring

| Difficulté | Sans hint | Avec hint |
|------------|-----------|-----------|
| SIMPLE | 10 | 5 |
| VICIEUX | 15 | 10 |
| TORDU | 25 | 15 |
| DIABOLIQUE | 50 | 25 |
| IMPOSSIBLE | 80 | 40 |

- La difficulté (pas le type) détermine les points.
- Une fois la bonne réponse trouvée, la partie est terminée et ne peut plus être rejouée
- Les points contribuent au classement global et au classement mensuel
- Les points gagnés = tokens pixia gagnés
