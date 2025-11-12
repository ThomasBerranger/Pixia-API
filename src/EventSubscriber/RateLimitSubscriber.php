<?php

namespace App\EventSubscriber;

use App\Attribute\RateLimit;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

readonly class RateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory    $globalApiLimiter,
        private RateLimiterFactory    $gameAnswerLimiter,
//        private RateLimiterFactory    $authFormLimiter,
        private TokenStorageInterface $tokenStorage, private LoggerInterface $logger,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 10],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $token = $this->tokenStorage->getToken();
        $identifier = $token?->getUserIdentifier() ?? $request->getClientIp();

        $limiter = $this->globalApiLimiter->create($identifier);

        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(
                message: 'Trop de requêtes, ralentis un peu !'
            );
        }

        $this->applyAttributeRateLimit($event);
    }

    private function applyAttributeRateLimit(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        $method = new \ReflectionMethod($controller[0]::class, $controller[1]);
        $attributes = $method->getAttributes(RateLimit::class);

        if (empty($attributes)) {
            return;
        }

        /** @var RateLimit $rateLimit */
        $rateLimit = $attributes[0]->newInstance();

        $this->logger->info($rateLimit->limiterName);

        // Sélectionne le bon limiter
        $limiterFactory = match($rateLimit->limiterName) {
            'game_answer' => $this->gameAnswerLimiter,
//            'auth_forms' => $this->authFormLimiter,
            default => throw new \InvalidArgumentException("Unknown rate limiter: {$rateLimit->limiterName}")
        };

        $identifier = $event->getRequest()->getClientIp() . $event->getRequest()->get('id');

        $limiter = $limiterFactory->create($identifier);

        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(
                message: 'Trop de tentatives, fais une petite pause ou essaie une autre image.'
            );
        }
    }
}
