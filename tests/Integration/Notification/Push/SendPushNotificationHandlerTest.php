<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification\Push;

use App\Entity\User\UserNotificationPreference;
use App\Enum\Notification\PushCategory;
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

    /** Read when sent, so a category switched off also drops the pushes already queued (#1110). */
    public function test_a_category_switched_off_sends_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'mute_chat', 'email' => 'mute.chat@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);
        $preference = new UserNotificationPreference();
        $preference->user = $user;
        $preference->pushBandChat = false;
        $user->notificationPreference = $preference;
        \Zenstruck\Foundry\Persistence\save($preference);

        $this->handler()(new SendPushNotification((string) $user->id, 'Titre', 'Corps', [], PushCategory::BandChat));
        $this->assertSame([], self::getContainer()->get(RecordingFirebaseMessaging::class)->multicasts);

        $this->handler()(new SendPushNotification((string) $user->id, 'Titre', 'Corps', [], PushCategory::BandMention));
        $this->assertCount(1, self::getContainer()->get(RecordingFirebaseMessaging::class)->multicasts);
    }

    public function test_without_preferences_every_category_is_on(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'no_prefs', 'email' => 'no.prefs@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);

        $this->handler()(new SendPushNotification((string) $user->id, 'Titre', 'Corps', [], PushCategory::BandChat));

        $this->assertCount(1, self::getContainer()->get(RecordingFirebaseMessaging::class)->multicasts);
    }

    public function test_a_suspended_account_gets_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'suspended_user',
            'email' => 'suspended.user@example.com',
            'suspensionDatetime' => new \DateTimeImmutable('2026-10-01'),
        ]);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);

        $this->handler()(new SendPushNotification((string) $user->id, 'Titre', 'Corps'));

        $this->assertSame([], self::getContainer()->get(RecordingFirebaseMessaging::class)->multicasts);
    }

    /** A band deletion is pushed whatever the switches say. */
    public function test_an_always_push_ignores_every_switch(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'all_off', 'email' => 'all.off@example.com']);
        DeviceTokenFactory::new()->create(['user' => $user, 'token' => self::PHONE]);
        $preference = new UserNotificationPreference();
        $preference->user = $user;
        foreach (PushCategory::cases() as $category) {
            $property = 'push' . str_replace('_', '', ucwords($category->value, '_'));
            if (property_exists($preference, $property)) {
                $preference->{$property} = false;
            }
        }
        $user->notificationPreference = $preference;
        \Zenstruck\Foundry\Persistence\save($preference);

        $this->handler()(new SendPushNotification((string) $user->id, 'Titre', 'Corps', [], PushCategory::Always));

        $this->assertCount(1, self::getContainer()->get(RecordingFirebaseMessaging::class)->multicasts);
        $this->assertFalse($preference->pushBandMembership);
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
