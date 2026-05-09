<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

#[AsEventListener(event: 'kernel.response')]
final class BandSpaceFileQuotaApproachingHeaderListener
{
    public const string REQUEST_ATTRIBUTE = '_band_space_quota_approaching';

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->getBoolean(self::REQUEST_ATTRIBUTE)) {
            $event->getResponse()->headers->set('X-Quota-Approaching', 'true');
        }
    }
}
