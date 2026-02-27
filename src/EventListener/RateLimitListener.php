<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class RateLimitListener
{
    public function __construct(
        private RateLimiterFactory $authLoginLimiter,
        private RateLimiterFactory $authRegisterLimiter,
        private RateLimiterFactory $authForgotPasswordLimiter,
        private RateLimiterFactory $authResetPasswordLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->isMethod('POST')) {
            return;
        }

        $path = $request->getPathInfo();
        $ip = $request->getClientIp();

        $limiter = match ($path) {
            '/api/login' => $this->authLoginLimiter,
            '/api/register' => $this->authRegisterLimiter,
            '/api/auth/forgot-password' => $this->authForgotPasswordLimiter,
            '/api/auth/reset-password' => $this->authResetPasswordLimiter,
            default => null,
        };

        if ($limiter === null) {
            return;
        }

        $limit = $limiter->create($ip)->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

            $event->setResponse(new JsonResponse(
                ['error' => 'Trop de tentatives. Réessayez dans ' . $retryAfter . ' secondes.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $retryAfter]
            ));
        }
    }
}
