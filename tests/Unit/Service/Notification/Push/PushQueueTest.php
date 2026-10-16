<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification\Push;

use App\Entity\User;
use App\Enum\Notification\PushCategory;
use App\Messenger\SendPushNotification;
use App\Service\Notification\Push\PushContent;
use App\Service\Notification\Push\PushQueue;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PushQueueTest extends TestCase
{
    /** A locale whose push cannot be built costs its own recipients the push, logged once, and nobody else. */
    public function test_a_locale_that_fails_to_build_is_logged_once_and_skipped(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $dispatched = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message);
            }
        };
        $logger = new BufferingLogger();
        $failure = new \RuntimeException('No catalogue');
        $builtLocales = [];

        (new PushQueue($bus, $logger))->queue(
            [$this->user('fr-1', 'fr'), $this->user('en-1', 'en'), $this->user('fr-2', 'fr'), $this->user('en-2', 'en')],
            static function (string $locale) use (&$builtLocales, $failure): PushContent {
                $builtLocales[] = $locale;
                if ($locale === 'en') {
                    throw $failure;
                }

                return new PushContent('Les Cactus', 'alice a quitté « Les Cactus »', ['type' => 'band_space_member_left']);
            },
            PushCategory::BandMembership,
        );

        $this->assertSame(['fr', 'en'], $builtLocales);
        $this->assertSame(['fr-1', 'fr-2'], array_map(static fn (object $push): string => $push instanceof SendPushNotification ? $push->userId : $push::class, $bus->dispatched));
        $this->assertSame([[
            LogLevel::ERROR,
            'Could not build a push notification',
            ['exception' => $failure, 'locale' => 'en', 'user_count' => 2],
        ]], $logger->cleanLogs());
    }

    private function user(string $id, string $locale): User
    {
        $user = new User();
        $user->id = $id;
        $user->locale = $locale;

        return $user;
    }
}
