<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification\Push;

use App\Messenger\SendPushNotification;
use App\Messenger\SendPushNotificationHandler;
use App\Tests\Double\RecordingFirebaseMessaging;
use App\Tests\Factory\User\DeviceTokenFactory;
use App\Tests\Factory\User\UserFactory;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SendPushNotificationHandlerTest extends KernelTestCase
{
    private const string PHONE = 'hJ4kL5mN6oP7qR8sT9uV0w:APA91bD2fG3hJ4kL5zX6cV7bN8mQ9wE0rT1yU2iO3pA4sD5fG6hJ7kL8zX9cV0bN1mQ2wE3rT4yU5iO6pA7sD8fG9hJ0kL1zX2cV3bN4mQ5wE6rT7yU8iO9pA0sD1fG2hJ3kL4';

    public function test_pushes_to_the_users_devices(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'rose_sitar', 'email' => 'rose.sitar@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);

        $this->handler()(new SendPushNotification((string) $user->id, 'Titre', 'Corps', ['route' => '/band/7/chat']));

        $messaging = self::getContainer()->get(RecordingFirebaseMessaging::class);
        $this->assertCount(1, $messaging->multicasts);
        $this->assertSame([self::PHONE], $messaging->multicasts[0]['tokens']);
    }

    public function test_a_deleted_account_gets_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'gone_user',
            'email' => 'gone.user@example.com',
            'deletionDatetime' => new \DateTimeImmutable('2026-09-01'),
        ]);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);

        $this->handler()(new SendPushNotification((string) $user->id, 'Titre', 'Corps'));

        $this->assertSame([], self::getContainer()->get(RecordingFirebaseMessaging::class)->multicasts);
    }

    public function test_fcm_unavailable_is_handed_back_to_messenger_for_a_retry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'ali_oud', 'email' => 'ali.oud@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);
        self::getContainer()->get(RecordingFirebaseMessaging::class)
            ->failToken(self::PHONE, new ServerUnavailable('The service is currently unavailable'));

        $this->expectException(RecoverableMessageHandlingException::class);

        $this->handler()(new SendPushNotification((string) $user->id, 'Titre', 'Corps'));
    }

    private function handler(): SendPushNotificationHandler
    {
        return self::getContainer()->get(SendPushNotificationHandler::class);
    }
}
