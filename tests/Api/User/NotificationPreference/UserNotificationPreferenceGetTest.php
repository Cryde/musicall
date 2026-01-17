<?php

declare(strict_types=1);

namespace App\Tests\Api\User\NotificationPreference;

use App\Entity\User\UserNotificationPreference;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserNotificationPreferenceGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_notification_preferences_returns_defaults_when_no_entity_exists(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'notifuser',
            'email' => 'notifuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/notification-preferences');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotificationPreferenceEdit',
            '@id' => '/api/user/notification-preferences',
            '@type' => 'UserNotificationPreferenceEdit',
            'site_news' => true,
            'weekly_recap' => true,
            'message_received' => true,
            'publication_comment' => true,
            'forum_reply' => true,
            'marketing' => false,
            'activity_reminder' => true,
        ]);
    }

    public function test_get_notification_preferences_returns_saved_values(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'savednotifuser',
            'email' => 'savednotifuser@test.com',
        ]);

        $preference = new UserNotificationPreference();
        $preference->setUser($user->_real());
        $preference->setSiteNews(false);
        $preference->setWeeklyRecap(true);
        $preference->setMessageReceived(false);
        $preference->setPublicationComment(true);
        $preference->setForumReply(false);
        $preference->setMarketing(true);
        $preference->setActivityReminder(false);
        $user->_real()->setNotificationPreference($preference);
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/notification-preferences');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotificationPreferenceEdit',
            '@id' => '/api/user/notification-preferences',
            '@type' => 'UserNotificationPreferenceEdit',
            'site_news' => false,
            'weekly_recap' => true,
            'message_received' => false,
            'publication_comment' => true,
            'forum_reply' => false,
            'marketing' => true,
            'activity_reminder' => false,
        ]);
    }

    public function test_get_notification_preferences_requires_authentication(): void
    {
        $this->client->request('GET', '/api/user/notification-preferences');
        $this->assertResponseStatusCodeSame(401);
    }
}
