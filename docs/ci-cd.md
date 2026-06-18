# CI/CD & qualité

## Pipeline GitHub Actions

**Job `tests`** (avec MySQL 8.0)
1. Setup PHP 8.4
2. `composer install`
3. `vendor/bin/phpunit`

**Job `code-quality`**
1. `vendor/bin/php-cs-fixer fix --dry-run --diff`
2. `composer stan` → PHPStan level max

## Bloque le merge
- Tout test PHPUnit en échec
- Toute violation PHP-CS-Fixer
- Toute erreur PHPStan

## Commandes utiles
```bash
composer test   # PHPUnit
composer cs     # PHP-CS-Fixer (applique)
composer stan   # PHPStan
```

## Déploiement
- Automatique sur Railway à chaque push sur `main`
