<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SecurityHeadersSubscriber
{
    public function __construct(
        private readonly bool $appIndexable,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        $path = $event->getRequest()->getPathInfo();
        if (!$this->appIndexable || str_starts_with($path, '/administration')) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}
