<?php

declare(strict_types=1);

namespace App\Tests\Api\Comment\Post;

use App\Entity\Comment\CommentThread;
use App\Entity\Publication;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Repository\Comment\CommentRepository;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Comment\CommentFactory;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class CommentPostNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_comment_notifies_author_and_prior_commenters_not_the_commenter(): void
    {
        $author = UserFactory::new()->create(['username' => 'pub_author', 'email' => 'pa@test.com']);
        $commenterB = UserFactory::new()->create(['username' => 'commenter_b', 'email' => 'b@test.com']);
        $commenterC = UserFactory::new()->create(['username' => 'commenter_c', 'email' => 'c@test.com']);
        $poster = UserFactory::new()->asBaseUser()->create(['username' => 'poster', 'email' => 'poster@test.com']);

        $thread = CommentThreadFactory::new()->create();
        $publication = $this->createPublicationWithThread($author, $thread);
        CommentFactory::new(['thread' => $thread, 'author' => $commenterB, 'content' => 'Premier commentaire'])->create();
        CommentFactory::new(['thread' => $thread, 'author' => $commenterC, 'content' => 'Deuxième commentaire'])->create();

        $this->client->loginUser($poster);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $thread->id,
            'content' => 'Super publication, merci !',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $newComment = self::getContainer()->get(CommentRepository::class)->findOneBy(['author' => $poster]);
        self::assertNotNull($newComment);

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $expectedPayload = [
            'publication_id' => (string) $publication->id,
            'publication_slug' => 'ma-publication-42',
            'publication_title' => 'Ma publication',
            'is_course' => false,
            'comment_id' => (string) $newComment->id,
            'actor_id' => (string) $poster->id,
            'actor_username' => 'poster',
        ];

        foreach ([$author, $commenterB, $commenterC] as $recipient) {
            $notifications = $notificationRepository->findForRecipient($recipient, 10, 0);
            $this->assertCount(1, $notifications);
            $this->assertSame(NotificationType::PublicationComment, $notifications[0]->type);
            $this->assertSame($expectedPayload, $notifications[0]->payload);
        }

        $this->assertCount(0, $notificationRepository->findForRecipient($poster, 10, 0));
    }

    public function test_reply_notifies_thread_including_parent_author_with_reply_type(): void
    {
        $author = UserFactory::new()->create(['username' => 'pub_author', 'email' => 'pa@test.com']);
        $parentAuthor = UserFactory::new()->create(['username' => 'parent_author', 'email' => 'parent@test.com']);
        $replier = UserFactory::new()->asBaseUser()->create(['username' => 'replier', 'email' => 'replier@test.com']);

        $thread = CommentThreadFactory::new()->create();
        $publication = $this->createPublicationWithThread($author, $thread);
        $parent = CommentFactory::new(['thread' => $thread, 'author' => $parentAuthor, 'content' => 'Question initiale'])->create();

        $this->client->loginUser($replier);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'   => '/api/comment_threads/' . $thread->id,
            'content'  => 'Voici la réponse',
            'parentId' => $parent->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $reply = self::getContainer()->get(CommentRepository::class)->findOneBy(['parent' => $parent->id]);
        self::assertNotNull($reply);

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $expectedPayload = [
            'publication_id' => (string) $publication->id,
            'publication_slug' => 'ma-publication-42',
            'publication_title' => 'Ma publication',
            'is_course' => false,
            'comment_id' => (string) $reply->id,
            'actor_id' => (string) $replier->id,
            'actor_username' => 'replier',
        ];

        // The parent author is both a thread commenter and the parent author -> single deduped row.
        foreach ([$author, $parentAuthor] as $recipient) {
            $notifications = $notificationRepository->findForRecipient($recipient, 10, 0);
            $this->assertCount(1, $notifications);
            $this->assertSame(NotificationType::CommentReply, $notifications[0]->type);
            $this->assertSame($expectedPayload, $notifications[0]->payload);
        }

        $this->assertCount(0, $notificationRepository->findForRecipient($replier, 10, 0));
    }

    public function test_comment_on_a_course_sets_is_course_true(): void
    {
        $author = UserFactory::new()->create(['username' => 'course_author', 'email' => 'ca@test.com']);
        $poster = UserFactory::new()->asBaseUser()->create(['username' => 'student', 'email' => 'student@test.com']);

        $thread = CommentThreadFactory::new()->create();
        $publication = $this->createPublicationWithThread($author, $thread, isCourse: true);

        $this->client->loginUser($poster);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $thread->id,
            'content' => 'Merci pour ce cours !',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $newComment = self::getContainer()->get(CommentRepository::class)->findOneBy(['author' => $poster]);
        self::assertNotNull($newComment);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::PublicationComment, $notifications[0]->type);
        $this->assertSame([
            'publication_id' => (string) $publication->id,
            'publication_slug' => 'mon-cours-42',
            'publication_title' => 'Mon cours',
            'is_course' => true,
            'comment_id' => (string) $newComment->id,
            'actor_id' => (string) $poster->id,
            'actor_username' => 'student',
        ], $notifications[0]->payload);
    }

    public function test_comment_by_the_publication_author_does_not_notify_themselves(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'solo_author', 'email' => 'solo@test.com']);
        $thread = CommentThreadFactory::new()->create();
        $this->createPublicationWithThread($author, $thread);

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $thread->id,
            'content' => 'Note pour plus tard',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0));
    }

    public function test_comment_on_a_non_publication_thread_creates_no_notification(): void
    {
        // A CommentThread not owned by any Publication: the listener resolves no publication and
        // skips gracefully - the comment is still created (201) and no notification row is written.
        $commenter = UserFactory::new()->asBaseUser()->create(['username' => 'lone_commenter', 'email' => 'lone@test.com']);
        $thread = CommentThreadFactory::new()->create();

        $this->client->loginUser($commenter);
        $this->client->jsonRequest('POST', '/api/comments', [
            'thread'  => '/api/comment_threads/' . $thread->id,
            'content' => 'Commentaire sur un fil sans publication',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findAll());
    }

    private function createPublicationWithThread(User $author, CommentThread $thread, bool $isCourse = false): Publication
    {
        $subCategory = $isCourse
            ? PublicationSubCategoryFactory::new()->asCourse()
            : PublicationSubCategoryFactory::new()->asArticle();

        return PublicationFactory::new([
            'author' => $author,
            'subCategory' => $subCategory,
            'thread' => $thread,
            'slug' => $isCourse ? 'mon-cours-42' : 'ma-publication-42',
            'title' => $isCourse ? 'Mon cours' : 'Ma publication',
        ])->create();
    }
}
