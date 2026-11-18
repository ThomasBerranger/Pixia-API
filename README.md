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

## Qualité

```bash
composer test    # PHPUnit
composer stan    # PHPStan niveau max
composer cs      # PHP-CS-Fixer
```

La CI bloque le merge sur tout test en échec, toute violation de style et toute erreur PHPStan.

## Auteur

Projet conçu et développé par [Thomas Berranger](https://github.com/ThomasBerranger) aka Mirage.
