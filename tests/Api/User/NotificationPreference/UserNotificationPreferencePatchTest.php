<?php

declare(strict_types=1);

namespace App\Tests\Api\User\NotificationPreference;

use App\Entity\User\UserNotificationPreference;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserNotificationPreferencePatchTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_patch_notification_preferences_creates_entity_when_none_exists(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'newprefuser',
            'email' => 'newprefuser@test.com',
        ]);

        $this->assertNull($user->_real()->getNotificationPreference());

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/notification-preferences', [
            'site_news' => false,
            'marketing' => true,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotificationPreferenceEdit',
            '@id' => '/api/user/notification-preferences',
            '@type' => 'UserNotificationPreferenceEdit',
            'site_news' => false,
            'weekly_recap' => true,
            'message_received' => true,
            'publication_comment' => true,
            'forum_reply' => true,
            'marketing' => true,
            'activity_reminder' => true,
        ]);

        $user->_refresh();
        $this->assertNotNull($user->_real()->getNotificationPreference());
    }

    public function test_patch_notification_preferences_updates_existing_entity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'existingprefuser',
            'email' => 'existingprefuser@test.com',
        ]);

        $preference = new UserNotificationPreference();
        $preference->setUser($user->_real());
        $preference->setSiteNews(true);
        $preference->setWeeklyRecap(true);
        $preference->setMessageReceived(true);
        $preference->setPublicationComment(true);
        $preference->setForumReply(true);
        $preference->setMarketing(false);
        $preference->setActivityReminder(true);
        $user->_real()->setNotificationPreference($preference);
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/notification-preferences', [
            'message_received' => false,
            'forum_reply' => false,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotificationPreferenceEdit',
            '@id' => '/api/user/notification-preferences',
            '@type' => 'UserNotificationPreferenceEdit',
            'site_news' => true,
            'weekly_recap' => true,
            'message_received' => false,
            'publication_comment' => true,
            'forum_reply' => false,
            'marketing' => false,
            'activity_reminder' => true,
        ]);
    }

    public function test_patch_notification_preferences_updates_all_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'allfielduser',
            'email' => 'allfielduser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/notification-preferences', [
            'site_news' => false,
            'weekly_recap' => false,
            'message_received' => false,
            'publication_comment' => false,
            'forum_reply' => false,
            'marketing' => true,
            'activity_reminder' => false,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserNotificationPreferenceEdit',
            '@id' => '/api/user/notification-preferences',
            '@type' => 'UserNotificationPreferenceEdit',
            'site_news' => false,
            'weekly_recap' => false,
            'message_received' => false,
            'publication_comment' => false,
            'forum_reply' => false,
            'marketing' => true,
            'activity_reminder' => false,
        ]);
    }

    public function test_patch_notification_preferences_requires_authentication(): void
    {
        $this->client->jsonRequest('PATCH', '/api/user/notification-preferences', [
            'site_news' => false,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(401);
    }
}
