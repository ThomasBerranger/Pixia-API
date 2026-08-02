<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\AppCompatibility;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rejette en 426 (Upgrade Required) les fronts dont la version est antérieure
 * à AppCompatibility::MIN_APP_VERSION. Le front intercepte ce statut pour
 * forcer sa mise à jour. Une requête sans header de version passe (clients
 * hors navigateur, anciens clients sans la feature).
 */
class AppVersionSubscriber implements EventSubscriberInterface
{
    public const string APP_VERSION_HEADER = 'X-App-Version';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $appVersion = $event->getRequest()->headers->get(self::APP_VERSION_HEADER);

        // Header absent (clients hors navigateur, anciens clients) ou non-semver
        // (valeur malformée) → on laisse passer plutôt que de bloquer à tort.
        if ($appVersion === null || preg_match('/^\d+\.\d+\.\d+$/', $appVersion) !== 1) {
            return;
        }

        if (version_compare($appVersion, AppCompatibility::MIN_APP_VERSION, '<')) {
            $event->setResponse(new JsonResponse([
                'error' => 'Client outdated',
                'minVersion' => AppCompatibility::MIN_APP_VERSION,
            ], 426));
        }
    }
}
