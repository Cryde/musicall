<?php

declare(strict_types=1);

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Entity\User;
use App\Entity\User\UserNotificationPreference;
use App\Enum\Notification\NotificationType;
use App\Mercure\MercureTopic;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\Metric\VoteCacheFactory;
use App\Tests\Factory\Metric\VoteFactory;
use App\Tests\Factory\Notification\NotificationFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Zenstruck\Foundry\Attribute\ResetDatabase;

use function Zenstruck\Foundry\Persistence\save;

#[ResetDatabase]
class PublicationVoteNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array SERVER_PARAMS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_a_first_up_vote_notifies_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $publication = $this->createPublication($author);
        $voter = UserFactory::new()->create(['username' => 'voter', 'email' => 'voter@test.com']);

        $this->client->loginUser($voter);
        $this->vote(1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 1, downvotes: 0, userVote: 1));

        $notifications = $this->notificationsOf($author);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::PublicationVoted, $notifications[0]->type);
        $this->assertNull($notifications[0]->readDatetime);
        $this->assertSame([
            'publication_id' => (string) $publication->id,
            'publication_slug' => 'mon-article',
            'publication_title' => 'Mon article',
            'is_course' => false,
            'vote_count' => 1,
        ], $notifications[0]->payload);
    }

    public function test_an_anonymous_up_vote_on_a_course_notifies_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $course = $this->createPublication($author, PublicationSubCategoryFactory::new()->asCourse()->create());

        $this->vote(1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 1, downvotes: 0, userVote: 1));

        $notifications = $this->notificationsOf($author);
        $this->assertCount(1, $notifications);
        $this->assertSame([
            'publication_id' => (string) $course->id,
            'publication_slug' => 'mon-article',
            'publication_title' => 'Mon article',
            'is_course' => true,
            'vote_count' => 1,
        ], $notifications[0]->payload);
    }

    public function test_an_up_vote_bumps_the_unread_notification_of_that_publication(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $publication = $this->createPublication($author);
        $publicationId = (string) $publication->id;
        $voter = UserFactory::new()->create(['username' => 'voter', 'email' => 'voter@test.com']);

        $unread = NotificationFactory::new([
            'recipient' => $author,
            'type' => NotificationType::PublicationVoted,
            'payload' => [
                'publication_id' => $publicationId,
                'publication_slug' => 'mon-article',
                'publication_title' => 'Ancien titre',
                'is_course' => false,
                'vote_count' => 2,
            ],
            'creationDatetime' => new DateTimeImmutable('2026-01-01 10:00:00'),
        ])->create();
        $unreadId = $unread->id;
        // Another publication's row stays as it is: the grouping is per publication.
        $other = NotificationFactory::new([
            'recipient' => $author,
            'type' => NotificationType::PublicationVoted,
            'payload' => ['publication_id' => '999999', 'vote_count' => 4],
            'creationDatetime' => new DateTimeImmutable('2026-01-02 10:00:00'),
        ])->create();
        $otherId = $other->id;

        $before = new DateTimeImmutable('-1 minute');
        $this->client->loginUser($voter);
        $this->vote(1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 1, downvotes: 0, userVote: 1));

        $notifications = $this->notificationsOf($author);
        $this->assertCount(2, $notifications);
        // Back on top of the feed, which orders by creation date.
        $this->assertSame($unreadId, $notifications[0]->id);
        $this->assertGreaterThan($before, $notifications[0]->creationDatetime);
        $this->assertSame([
            'publication_id' => $publicationId,
            'publication_slug' => 'mon-article',
            'publication_title' => 'Mon article',
            'is_course' => false,
            'vote_count' => 3,
        ], $notifications[0]->payload);
        $this->assertSame($otherId, $notifications[1]->id);
        $this->assertSame(['publication_id' => '999999', 'vote_count' => 4], $notifications[1]->payload);

        $hub = self::getContainer()->get(RecordingHub::class);
        $this->assertSame([MercureTopic::userNotifications($author->id)], $hub->publishedTopics());
    }

    public function test_an_up_vote_after_the_author_read_the_previous_one_starts_a_new_row(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $publication = $this->createPublication($author);
        $publicationId = (string) $publication->id;
        $voter = UserFactory::new()->create(['username' => 'voter', 'email' => 'voter@test.com']);

        $read = NotificationFactory::new([
            'recipient' => $author,
            'type' => NotificationType::PublicationVoted,
            'payload' => ['publication_id' => $publicationId, 'vote_count' => 2],
            'creationDatetime' => new DateTimeImmutable('2026-01-01 10:00:00'),
        ])->read(new DateTimeImmutable('2026-01-01 11:00:00'))->create();
        $readId = $read->id;

        $this->client->loginUser($voter);
        $this->vote(1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 1, downvotes: 0, userVote: 1));

        $notifications = $this->notificationsOf($author);
        $this->assertCount(2, $notifications);
        $this->assertNotSame($readId, $notifications[0]->id);
        $this->assertSame([
            'publication_id' => $publicationId,
            'publication_slug' => 'mon-article',
            'publication_title' => 'Mon article',
            'is_course' => false,
            'vote_count' => 1,
        ], $notifications[0]->payload);
        $this->assertSame($readId, $notifications[1]->id);
        $this->assertSame(['publication_id' => $publicationId, 'vote_count' => 2], $notifications[1]->payload);
    }

    public function test_a_down_vote_turned_up_notifies_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $publication = $this->createPublication($author);
        $voter = UserFactory::new()->create(['username' => 'voter', 'email' => 'voter@test.com']);
        $this->seedVote($publication, $voter, -1);

        $this->client->loginUser($voter);
        $this->vote(1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 1, downvotes: 0, userVote: 1));
        $notifications = $this->notificationsOf($author);
        $this->assertCount(1, $notifications);
        $this->assertSame([
            'publication_id' => (string) $publication->id,
            'publication_slug' => 'mon-article',
            'publication_title' => 'Mon article',
            'is_course' => false,
            'vote_count' => 1,
        ], $notifications[0]->payload);
    }

    public function test_the_author_voting_on_their_own_publication_is_not_notified(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $this->createPublication($author);

        $this->client->loginUser($author);
        $this->vote(1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 1, downvotes: 0, userVote: 1));
        $this->assertSame([], $this->notificationsOf($author));
    }

    public function test_a_down_vote_does_not_notify(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $this->createPublication($author);
        $voter = UserFactory::new()->create(['username' => 'voter', 'email' => 'voter@test.com']);

        $this->client->loginUser($voter);
        $this->vote(-1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 0, downvotes: 1, userVote: -1));
        $this->assertSame([], $this->notificationsOf($author));
    }

    public function test_withdrawing_an_up_vote_does_not_notify(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $publication = $this->createPublication($author);
        $voter = UserFactory::new()->create(['username' => 'voter', 'email' => 'voter@test.com']);
        $this->seedVote($publication, $voter, 1);

        $this->client->loginUser($voter);
        $this->vote(1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 0, downvotes: 0, userVote: null));
        $this->assertSame([], $this->notificationsOf($author));
    }

    public function test_an_author_who_turned_the_switch_off_is_not_notified(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $preference = new UserNotificationPreference();
        $preference->user = $author;
        $preference->publicationVote = false;
        $author->notificationPreference = $preference;
        save($author);
        $this->createPublication($author);
        $voter = UserFactory::new()->create(['username' => 'voter', 'email' => 'voter@test.com']);

        $this->client->loginUser($voter);
        $this->vote(1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 1, downvotes: 0, userVote: 1));
        $this->assertSame([], $this->notificationsOf($author));
    }

    public function test_a_deleted_author_is_not_notified(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['deletionDatetime' => new DateTimeImmutable('2026-01-01')]);
        $this->createPublication($author);
        $voter = UserFactory::new()->create(['username' => 'voter', 'email' => 'voter@test.com']);

        $this->client->loginUser($voter);
        $this->vote(1);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->summary(upvotes: 1, downvotes: 0, userVote: 1));
        $this->assertSame([], $this->notificationsOf($author));
    }

    private function vote(int $value): void
    {
        $this->client->jsonRequest('POST', '/api/publications/mon-article/vote', ['user_vote' => $value], self::SERVER_PARAMS);
    }

    /** @return array<string, mixed> */
    private function summary(int $upvotes, int $downvotes, ?int $userVote): array
    {
        return [
            '@context' => '/api/contexts/PublicationVoteSummary',
            '@id' => '/api/publication_vote_summaries/mon-article',
            '@type' => 'PublicationVoteSummary',
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'user_vote' => $userVote,
        ];
    }

    private function createPublication(User $author, ?object $subCategory = null): Publication
    {
        return PublicationFactory::new([
            'author' => $author,
            'slug' => 'mon-article',
            'title' => 'Mon article',
            'status' => Publication::STATUS_ONLINE,
            'subCategory' => $subCategory ?? PublicationSubCategoryFactory::new()->asArticle()->create(),
            'type' => Publication::TYPE_TEXT,
        ])->create();
    }

    private function seedVote(Publication $publication, User $voter, int $value): void
    {
        $voteCache = VoteCacheFactory::new([
            'upvoteCount' => $value === 1 ? 1 : 0,
            'downvoteCount' => $value === -1 ? 1 : 0,
        ])->create();
        $publication->voteCache = $voteCache;
        save($publication);

        VoteFactory::new([
            'voteCache' => $voteCache,
            'user' => $voter,
            'value' => $value,
            'identifier' => 'test-identifier',
            'entityType' => 'app_publication',
            'entityId' => (string) $publication->id,
        ])->create();
    }

    /** @return list<\App\Entity\Notification\Notification> */
    private function notificationsOf(User $recipient): array
    {
        return array_values(self::getContainer()->get(NotificationRepository::class)->findForRecipient($recipient, 10, 0));
    }
}
