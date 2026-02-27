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
        private RateLimiterFactory $apiAiLimiter,
        private RateLimiterFactory $apiUploadLimiter,
        private RateLimiterFactory $apiEmailLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $ip = $request->getClientIp();

        // Routes POST exactes
        if ($request->isMethod('POST')) {
            $limiter = match ($path) {
                '/api/login' => $this->authLoginLimiter,
                '/api/register' => $this->authRegisterLimiter,
                '/api/auth/forgot-password' => $this->authForgotPasswordLimiter,
                '/api/auth/reset-password' => $this->authResetPasswordLimiter,
                default => null,
            };

            if ($limiter !== null) {
                $this->applyLimit($event, $limiter, $ip);
                return;
            }
        }

        // Routes par préfixe (POST et GET)
        $limiter = match (true) {
            str_starts_with($path, '/api/ai/') => $this->apiAiLimiter,
            str_starts_with($path, '/api/upload/') => $this->apiUploadLimiter,
            str_contains($path, '/email/') && str_starts_with($path, '/api/edl/') => $this->apiEmailLimiter,
            str_starts_with($path, '/api/aide/') => $this->apiAiLimiter,
            default => null,
        };

        if ($limiter !== null) {
            $this->applyLimit($event, $limiter, $ip);
        }
    }

    private function applyLimit(RequestEvent $event, RateLimiterFactory $limiter, string $ip): void
    {
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
