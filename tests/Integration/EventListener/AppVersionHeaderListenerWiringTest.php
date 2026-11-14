<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventListener;

use App\EventListener\AppVersionHeaderListener;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The listener is registered on every response, with the build's version file resolved by the container.
 */
class AppVersionHeaderListenerWiringTest extends KernelTestCase
{
    public function test_the_listener_runs_on_kernel_response(): void
    {
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);

        $listenerClasses = array_map(
            static fn (mixed $listener): string => is_array($listener) && is_object($listener[0]) ? $listener[0]::class : get_debug_type($listener),
            $dispatcher->getListeners(KernelEvents::RESPONSE),
        );

        self::assertContains(AppVersionHeaderListener::class, $listenerClasses);
    }
}
