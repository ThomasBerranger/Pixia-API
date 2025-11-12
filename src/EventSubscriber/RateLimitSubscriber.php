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
        private RateLimiterFactory $globalApiLimiter,
        private RateLimiterFactory $gameAnswerLimiter,
        private RateLimiterFactory $authFormLimiter,
        private TokenStorageInterface $tokenStorage,
        private LoggerInterface $logger,
    ) {
    }

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

    /**
     * @throws \ReflectionException
     */
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->applyRateLimit($event, 'global_api');

        $this->applyAttributeRateLimit($event);
    }

    /**
     * @throws \ReflectionException
     */
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

        $this->applyRateLimit($event, $rateLimit->limiterName);
    }

    private function applyRateLimit(ControllerEvent $event, string $limiterName): void
    {
        $config = self::LIMITERS_CONFIG[$limiterName]
            ?? throw new \InvalidArgumentException("Unknown rate limiter: {$limiterName}");

        $limiterFactory = $this->{$config['factory']};

        $identifier = $event->getRequest()->getClientIp();

        if ($config['useAuthIdentifier']) {
            $token = $this->tokenStorage->getToken();
            $identifier = $token?->getUserIdentifier() ?? $identifier;
        }

        if ($config['identifierSuffix']) {
            $identifier .= '_'.$event->getRequest()->attributes->get($config['identifierSuffix']);
        }

        $limiter = $limiterFactory->create($identifier);

        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(message: $config['message']);
        }
    }
}
