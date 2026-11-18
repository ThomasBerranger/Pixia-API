# Pixia API

> Chaque jour, une image générée par IA cache un film, un personnage ou deux univers mélangés. À toi de les retrouver.

🖥️ **Application web : [Pixia](https://github.com/ThomasBerranger/Pixia)** (captures et démo)

Ce dépôt est l'**API** de Pixia : jeu quotidien, comptes, jetons, scoring, classements, badges, modération et génération de contenu par IA.

## Le concept

Pixia publie **une image par jour**, jouable par tous, connecté ou non. Le type de jeu varie d'un jour à l'autre :

| Mode | L'image montre | À deviner |
|---|---|---|
| **Multivers** | deux films fusionnés en une seule scène | les deux films |
| **Kawaii** | un personnage redessiné en version kawaii | le personnage |
| **Polychrome** | un film résumé en illustration minimaliste | le film |
| **Convergence** | plusieurs éléments d'un même film réunis | le film |

- **Tentatives illimitées**, avec une reconnaissance tolérante : accents, ordre des mots, fautes de frappe, titres FR ou EN.
- **Un indice** par partie pour les joueurs connectés, au prix d'environ la moitié des points.
- **Des points selon la difficulté**, convertis en jetons, avec un classement mensuel et des badges.
- **Les joueurs créent aussi des parties Multivers** : ils décrivent leur idée, l'IA l'analyse et génère l'image, puis les admins modèrent avant publication.

## Stack

- **PHP 8.4** et **Symfony 7.4**
- **Doctrine ORM 3** et **MySQL**
- **Lexik JWT** pour l'authentification, avec connexion Google OAuth
- **Brevo** pour les mails (récapitulatif mensuel, notifications)
- **Claude** (calcul de notoriété des films)
- **OpenAI** et **Grok** (génération d'images)
- **PHPUnit 12**, **PHPStan niveau max** et **PHP-CS-Fixer**
- Déployé sur **Railway**, CI sur **GitHub Actions**

## Symfony en pratique

L'API s'appuie sur les composants Symfony plutôt que sur du code maison, en suivant leurs conventions (attributs PHP 8, autowiring, injection par constructeur).

| Besoin | Composant et mise en œuvre |
|---|---|
| **Autorisations** | `GameVoter` et `UserVoter` pilotent les droits selon le statut d'une partie et le rôle de l'utilisateur. 20 contrôles `#[IsGranted]` dans les controllers, avec une hiérarchie de rôles (`ROLE_SUPER_ADMIN` hérite de `ROLE_ADMIN`). |
| **Entrées validées** | Les requêtes sont désérialisées dans des DTO typés avec `#[MapRequestPayload]` et validées par les contraintes `#[Assert\...]`. `ExceptionSubscriber` renvoie un `422` au format uniforme avec la liste des `violations`. |
| **Sorties maîtrisées** | `GameNormalizer` (Serializer) décide des champs exposés selon des groupes et un contexte : la solution et l'indice ne sortent que si le contexte l'autorise. |
| **Effets de bord** | `#[AsEntityListener]` sur les événements Doctrine (`prePersist`, `preUpdate`, `postPersist`, `postUpdate`) pour `GameListener`, et `#[AsEventListener]` pour les badges, les succès et les claims du JWT. |
| **Cycle requête/réponse** | 4 subscribers : rate limiting, headers de sécurité (CSP, `X-Frame-Options`, `nosniff`), gestion des erreurs, et refus en `426` des fronts trop anciens. |
| **Rate limiting** | Composant RateLimiter avec trois politiques adaptées à l'usage : `sliding_window` (800 requêtes par heure) pour l'API, `token_bucket` (50 par 10 minutes) pour les réponses de jeu, `fixed_window` (30 par jour) pour les formulaires d'authentification. Un attribut `#[RateLimit('nom')]` les branche sur les routes. |
| **Tâches planifiées** | 2 commandes Console : récapitulatif mensuel (avec `--dry-run`) et bots joueurs. |
| **Temps testable** | `ClockInterface` injecté dans les commandes et dans `GameDateService`, pour contrôler la date dans les tests. |
| **Mails** | Mailer avec transport Brevo, templates Twig, et lien de désinscription signé avec `UriSigner`. |
| **Authentification** | Lexik JWT enrichi par un listener, Google OAuth et réinitialisation de mot de passe via `symfonycasts/reset-password-bundle`. |
| **Données** | Doctrine ORM 3 avec 29 migrations versionnées, et 11 enums PHP (`GameType`, `GameStatus`, `UserRole`…) mappés en base. |

La base de code est homogène : 102 fichiers PHP, tous en `declare(strict_types=1)`, 29 classes `readonly`, analysés par **PHPStan niveau max** et formatés par **PHP-CS-Fixer**.

## Qualité

```bash
composer test    # PHPUnit
composer stan    # PHPStan niveau max
composer cs      # PHP-CS-Fixer
```

La CI bloque le merge sur tout test en échec, toute violation de style et toute erreur PHPStan.

## Auteur

Projet conçu et développé par [Thomas Berranger](https://github.com/ThomasBerranger) aka Mirage.
