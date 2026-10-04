<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification\Push;

use App\Repository\User\DeviceTokenRepository;
use App\Service\Notification\Push\PushNotifier;
use App\Service\Notification\Push\PushOutcome;
use App\Tests\Double\RecordingFirebaseMessaging;
use App\Tests\Factory\User\DeviceTokenFactory;
use App\Tests\Factory\User\UserFactory;
use Kreait\Firebase\Exception\Messaging\AuthenticationError;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PushNotifierTest extends KernelTestCase
{
    private const string PHONE = 'cX1aB2cD3eF4gH5iJ6kL7m:APA91bE1rT2yU3iO4pA5sD6fG7hJ8kL9zX0cV1bN2mQ3wE4rT5yU6iO7pA8sD9fG0hJ1kL2zX3cV4bN5mQ6wE7rT8yU9iO0pA1sD2fG3hJ4kL5zX6cV7bN8mQ9wE0rT1yU2iO3pA4sD5';
    private const string TABLET = 'tY9zX8wV7uT6sR5qP4oN3m:APA91bF9lK8jH7gF6dS5aP4oI3uY2tR1eW0qZ9xC8vB7nM6lK5jH4gF3dS2aP1oI0uY9tR8eW7qZ6xC5vB4nM3lK2jH1gF0dS9aP8oI7uY6tR5eW4qZ3xC2vB1nM0lK9jH8gF7dS6';

    private RecordingFirebaseMessaging $messaging;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->messaging = self::getContainer()->get(RecordingFirebaseMessaging::class);
    }

    public function test_sends_one_multicast_to_every_device_of_the_user(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'ines_piano', 'email' => 'ines.piano@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::TABLET]);
        DeviceTokenFactory::new()->create(['token' => 'someoneElse:APA91bQ' . str_repeat('x', 140)]);

        $outcome = $this->notifier()->sendToUser($user, 'Nouvelle répétition', 'Jeudi 20h au studio', ['route' => '/band/42/agenda']);

        $this->assertSame(PushOutcome::Finished, $outcome);
        $this->assertCount(1, $this->messaging->multicasts);
        $this->assertEqualsCanonicalizing([self::PHONE, self::TABLET], $this->messaging->multicasts[0]['tokens']);
        $this->assertSame([
            'data' => ['route' => '/band/42/agenda'],
            'notification' => ['title' => 'Nouvelle répétition', 'body' => 'Jeudi 20h au studio'],
            'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'musicall_default']],
        ], $this->messaging->multicasts[0]['message']);
    }

    public function test_a_user_without_device_is_a_no_op(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'tom_banjo', 'email' => 'tom.banjo@example.com']);

        $outcome = $this->notifier()->sendToUser($user, 'Titre', 'Corps');

        $this->assertSame(PushOutcome::Finished, $outcome);
        $this->assertSame([], $this->messaging->multicasts);
    }

    public function test_unregistered_and_invalid_tokens_are_deleted(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'lou_harp', 'email' => 'lou.harp@example.com']);
        $kept = DeviceTokenFactory::new()->create(['user' => $user]);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::TABLET]);
        $this->messaging->failToken(self::PHONE, NotFound::becauseTokenNotFound(self::PHONE));
        $this->messaging->failToken(self::TABLET, new InvalidMessage('The registration token is not a valid FCM registration token'));

        $outcome = $this->notifier(logger: $this->loggerExpectingErrors(0))->sendToUser($user, 'Titre', 'Corps');

        $this->assertSame(PushOutcome::Finished, $outcome);
        $this->assertSame([$kept->token], self::getContainer()->get(DeviceTokenRepository::class)->findTokensForUser($user));
    }

    public function test_other_failures_are_logged_and_keep_the_token(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'max_oboe', 'email' => 'max.oboe@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);
        $this->messaging->failToken(self::PHONE, new AuthenticationError('Request had invalid authentication credentials'));

        $outcome = $this->notifier(logger: $this->loggerExpectingErrors(1))->sendToUser($user, 'Titre', 'Corps');

        $this->assertSame(PushOutcome::Finished, $outcome);
        $this->assertSame([self::PHONE], self::getContainer()->get(DeviceTokenRepository::class)->findTokensForUser($user));
    }

    public function test_a_send_that_throws_is_logged_not_propagated(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'zoe_tuba', 'email' => 'zoe.tuba@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);
        $this->messaging->throwOnSend(new InvalidArgument('Invalid service account'));

        $outcome = $this->notifier(logger: $this->loggerExpectingErrors(1))->sendToUser($user, 'Titre', 'Corps');

        $this->assertSame(PushOutcome::Finished, $outcome);
    }

    public function test_fcm_unavailable_for_every_device_asks_for_a_retry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'eva_horn', 'email' => 'eva.horn@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::TABLET]);
        $this->messaging->failToken(self::PHONE, new ServerUnavailable('The service is currently unavailable'));
        $this->messaging->failToken(self::TABLET, new ServerUnavailable('The service is currently unavailable'));

        $this->assertSame(PushOutcome::RetryLater, $this->notifier()->sendToUser($user, 'Titre', 'Corps'));
    }

    public function test_no_retry_once_one_device_received_it(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'leo_viola', 'email' => 'leo.viola@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::TABLET]);
        $this->messaging->failToken(self::TABLET, new ServerUnavailable('The service is currently unavailable'));

        $this->assertSame(PushOutcome::Finished, $this->notifier()->sendToUser($user, 'Titre', 'Corps'));
    }

    private function notifier(?LoggerInterface $logger = null): PushNotifier
    {
        return new PushNotifier(
            $this->messaging,
            self::getContainer()->get(DeviceTokenRepository::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    private function loggerExpectingErrors(int $count): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly($count))->method('error');

        return $logger;
    }
}
