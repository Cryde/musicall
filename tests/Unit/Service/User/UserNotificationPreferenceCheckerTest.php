<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
use App\Service\User\UserNotificationPreferenceChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserNotificationPreferenceCheckerTest extends TestCase
{
    private UserNotificationPreferenceChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserNotificationPreferenceChecker();
    }

    #[DataProvider('provideCanReceiveNotificationTrueCases')]
    public function test_can_receive_notification_returns_true(string $method, ?UserNotificationPreference $preference): void
    {
        $user = $this->createStub(User::class);
        $user->method('getNotificationPreference')->willReturn($preference);

        $this->assertTrue($this->checker->$method($user));
    }

    #[DataProvider('provideCanReceiveNotificationFalseCases')]
    public function test_can_receive_notification_returns_false(string $method, ?UserNotificationPreference $preference): void
    {
        $user = $this->createStub(User::class);
        $user->method('getNotificationPreference')->willReturn($preference);

        $this->assertFalse($this->checker->$method($user));
    }

    /**
     * @return iterable<string, array{string, UserNotificationPreference|null}>
     */
    public static function provideCanReceiveNotificationTrueCases(): iterable
    {
        // When preference is null, all methods return true except marketing
        yield 'siteNews - no preference' => ['canReceiveSiteNewsNotification', null];
        yield 'weeklyRecap - no preference' => ['canReceiveWeeklyRecapNotification', null];
        yield 'message - no preference' => ['canReceiveMessageNotification', null];
        yield 'publicationComment - no preference' => ['canReceivePublicationCommentNotification', null];
        yield 'forumReply - no preference' => ['canReceiveForumReplyNotification', null];
        yield 'activityReminder - no preference' => ['canReceiveActivityReminderNotification', null];

        // When preference exists with flag enabled
        yield 'siteNews - enabled' => ['canReceiveSiteNewsNotification', self::createPreference(siteNews: true)];
        yield 'weeklyRecap - enabled' => ['canReceiveWeeklyRecapNotification', self::createPreference(weeklyRecap: true)];
        yield 'message - enabled' => ['canReceiveMessageNotification', self::createPreference(messageReceived: true)];
        yield 'publicationComment - enabled' => ['canReceivePublicationCommentNotification', self::createPreference(publicationComment: true)];
        yield 'forumReply - enabled' => ['canReceiveForumReplyNotification', self::createPreference(forumReply: true)];
        yield 'activityReminder - enabled' => ['canReceiveActivityReminderNotification', self::createPreference(activityReminder: true)];
        yield 'marketing - enabled' => ['canReceiveMarketingNotification', self::createPreference(marketing: true)];
    }

    /**
     * @return iterable<string, array{string, UserNotificationPreference|null}>
     */
    public static function provideCanReceiveNotificationFalseCases(): iterable
    {
        // Marketing returns false when preference is null (opt-in by default)
        yield 'marketing - no preference' => ['canReceiveMarketingNotification', null];

        // When preference exists with flag disabled
        yield 'siteNews - disabled' => ['canReceiveSiteNewsNotification', self::createPreference(siteNews: false)];
        yield 'weeklyRecap - disabled' => ['canReceiveWeeklyRecapNotification', self::createPreference(weeklyRecap: false)];
        yield 'message - disabled' => ['canReceiveMessageNotification', self::createPreference(messageReceived: false)];
        yield 'publicationComment - disabled' => ['canReceivePublicationCommentNotification', self::createPreference(publicationComment: false)];
        yield 'forumReply - disabled' => ['canReceiveForumReplyNotification', self::createPreference(forumReply: false)];
        yield 'activityReminder - disabled' => ['canReceiveActivityReminderNotification', self::createPreference(activityReminder: false)];
        yield 'marketing - disabled' => ['canReceiveMarketingNotification', self::createPreference(marketing: false)];
    }

    private static function createPreference(
        bool $siteNews = true,
        bool $weeklyRecap = true,
        bool $messageReceived = true,
        bool $publicationComment = true,
        bool $forumReply = true,
        bool $marketing = false,
        bool $activityReminder = true,
    ): UserNotificationPreference {
        $preference = new UserNotificationPreference();
        $preference->setSiteNews($siteNews);
        $preference->setWeeklyRecap($weeklyRecap);
        $preference->setMessageReceived($messageReceived);
        $preference->setPublicationComment($publicationComment);
        $preference->setForumReply($forumReply);
        $preference->setMarketing($marketing);
        $preference->setActivityReminder($activityReminder);

        return $preference;
    }
}
