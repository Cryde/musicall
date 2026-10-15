<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Locale\RequestLocaleResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

#[AsEventListener(event: KernelEvents::REQUEST, priority: self::PRIORITY)]
final readonly class RequestLocaleListener
{
    // Below the Firewall (8), which reads the stateless JWT. That is after the translator is set (15),
    // hence the LocaleSwitcher rather than only the request.
    public const int PRIORITY = 7;

    public function __construct(
        private RequestLocaleResolver $resolver,
        private LocaleSwitcher $localeSwitcher,
        private Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        $request = $event->getRequest();
        $locale = $this->resolver->resolve($request, $user instanceof User ? $user : null);

        $request->setLocale($locale);
        $this->localeSwitcher->setLocale($locale);
    }
}
