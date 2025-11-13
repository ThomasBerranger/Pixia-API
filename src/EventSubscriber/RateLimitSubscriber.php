<?php

namespace App\EventSubscriber;

use App\Attribute\RateLimit;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

readonly class RateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $globalApiLimiter,
        private RateLimiterFactory $gameAnswerLimiter,
        private RateLimiterFactory $authFormLimiter,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /** @var array<string, array{factory: string, message: string, identifierSuffix: string|null, useAuthIdentifier: bool}> */
    private const LIMITERS_CONFIG = [
        'global_api' => [
            'factory' => 'globalApiLimiter',
            'message' => 'Trop de requêtes, ralentis un peu !',
            'identifierSuffix' => null,
            'useAuthIdentifier' => true,
        ],
        'game_answer' => [
            'factory' => 'gameAnswerLimiter',
            'message' => 'Trop de tentatives, fais une petite pause ou essaie une autre image.',
            'identifierSuffix' => 'id',
            'useAuthIdentifier' => false,
        ],
        'auth_form' => [
            'factory' => 'authFormLimiter',
            'message' => 'Trop de tentatives de connexion, réessaie plus tard.',
            'identifierSuffix' => null,
            'useAuthIdentifier' => false,
        ],
    ];

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

        $this->applyRateLimit($event, 'global_api');

        $this->applyAttributeRateLimit($event);
    }

    private function applyAttributeRateLimit(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!is_array($controller) || 2 !== count($controller)) {
            return;
        }

        [$controllerObject, $methodName] = $controller;

        if (!is_object($controllerObject) || !is_string($methodName)) {
            return;
        }

        $method = new \ReflectionMethod($controllerObject::class, $methodName);
        $attributes = $method->getAttributes(RateLimit::class);

        if (empty($attributes)) {
            return;
        }

        /** @var RateLimit $rateLimit */
        $rateLimit = $attributes[0]->newInstance();

        $this->applyRateLimit($event, $rateLimit->limiterName);
    }

    private function applyRateLimit(ControllerEvent $event, string $limiterName): void
    {
        $config = self::LIMITERS_CONFIG[$limiterName]
            ?? throw new \InvalidArgumentException("Unknown rate limiter: {$limiterName}");

        $limiterFactory = $this->getLimiterFactory($config['factory']);

        $identifier = $event->getRequest()->getClientIp();

        if (!$identifier) {
            throw new \RuntimeException('Unable to determine client IP for rate limiting');
        }

        if ($config['useAuthIdentifier']) {
            $userIdentifier = $this->tokenStorage->getToken()?->getUserIdentifier();
            $identifier = $userIdentifier ?? $identifier;
        }

        if ($config['identifierSuffix']) {
            $suffix = $event->getRequest()->attributes->get($config['identifierSuffix']);

            if (!is_string($suffix) && !is_int($suffix)) {
                throw new \RuntimeException('Invalid identifier suffix: expected string or int');
            }

            $identifier .= '_'.$suffix;
        }

        $limiter = $limiterFactory->create($identifier);

        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(message: $config['message']);
        }
    }

    private function getLimiterFactory(string $factoryName): RateLimiterFactory
    {
        return match ($factoryName) {
            'globalApiLimiter' => $this->globalApiLimiter,
            'gameAnswerLimiter' => $this->gameAnswerLimiter,
            'authFormLimiter' => $this->authFormLimiter,
            default => throw new \InvalidArgumentException("Unknown limiter factory: {$factoryName}"),
        };
    }
}
