<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirige uniquement la variante www / apex du host canonique défini par DEFAULT_URI.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final class CanonicalHostSubscriber
{
    public function __construct(
        private readonly string $defaultUri,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $canonicalHost = parse_url($this->defaultUri, PHP_URL_HOST);
        $scheme = parse_url($this->defaultUri, PHP_URL_SCHEME);
        if (!is_string($canonicalHost) || $canonicalHost === '' || !is_string($scheme) || $scheme === '') {
            return;
        }

        $request = $event->getRequest();
        $requestHost = $request->getHost();
        if (strcasecmp($requestHost, $canonicalHost) === 0 || !$this->isWwwPair($requestHost, $canonicalHost)) {
            return;
        }

        $target = $scheme.'://'.$canonicalHost;
        $port = parse_url($this->defaultUri, PHP_URL_PORT);
        if (is_int($port) && !in_array($port, [80, 443], true)) {
            $target .= ':'.$port;
        }

        $target .= $request->getPathInfo();
        $query = $request->getQueryString();
        if (is_string($query) && $query !== '') {
            $target .= '?'.$query;
        }

        $status = $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308;
        $event->setResponse(new RedirectResponse($target, $status));
    }

    private function isWwwPair(string $requestHost, string $canonicalHost): bool
    {
        $requestBare = preg_replace('/^www\./i', '', strtolower($requestHost)) ?? strtolower($requestHost);
        $canonicalBare = preg_replace('/^www\./i', '', strtolower($canonicalHost)) ?? strtolower($canonicalHost);

        return $requestBare === $canonicalBare;
    }
}
