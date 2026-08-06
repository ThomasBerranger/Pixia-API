<?php

declare(strict_types=1);

namespace App;

/**
 * Version front minimale compatible avec le contrat de l'API.
 *
 * À bumper UNIQUEMENT lors d'un changement back cassant les anciens fronts.
 * Front et back étant déployés séparément, l'ordre est impératif pour ne pas
 * bloquer les clients à jour : déployer d'abord le front >= cette version et
 * vérifier qu'il est bien servi, PUIS déployer le bump back. Autrement dit,
 * MIN_APP_VERSION doit toujours rester <= à la version front réellement en
 * production. Les fronts en dessous reçoivent un 426 → rechargement forcé.
 */
final class AppCompatibility
{
    public const string MIN_APP_VERSION = '3.8.0';
}
