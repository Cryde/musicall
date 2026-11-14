<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Profile\ProfilePostCommentFactory;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** A post's comments a page at a time, and the page a notification opens (#1331). */
#[ResetDatabase]
class ProfilePostCommentPageTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_page_holds_twenty_comments_the_viewer_can_read(): void
    {
        $viewer = $this->member('chloe');
        $owner = $this->member('julien');
        $blocked = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blocked]);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $hiddenMinutes = [4, 12, 20];
        $comments = $this->commentsAt($post, $owner, array_diff(range(1, 24), $hiddenMinutes));
        $this->commentsAt($post, $blocked, $hiddenMinutes);
        // Neither a reply to a comment on a later page nor one by someone blocked shows on this one.
        $this->replyAt($comments[1], $owner, 30);
        $this->replyAt($comments[24], $blocked, 31);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments');

        $this->assertResponseIsSuccessful();
        // The hidden comments do not shorten the page: twenty readable ones, minute 1 waits for the next page.
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 20,
            'member' => array_map(static fn (int $minute): array => [
                '@id' => '/api/user/profile-post-comments/' . $comments[$minute]->id,
                '@type' => 'ProfilePostComment',
                'id' => $comments[$minute]->id,
                'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                'content' => 'Commentaire ' . $minute,
                'creation_datetime' => sprintf('2026-11-02T10:%02d:00+00:00', $minute),
                'can_delete' => false,
                'cursor' => self::cursor($comments[$minute]),
            ], array_values(array_diff(range(24, 2), $hiddenMinutes))),
        ]);
    }

    public function test_the_next_page_starts_after_the_cursor_and_brings_each_comment_with_its_replies(): void
    {
        $viewer = $this->member('chloe');
        $owner = $this->member('julien');
        $blocked = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blocked]);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $comments = $this->commentsAt($post, $owner, [1, 3]);
        $this->commentsAt($post, $blocked, [2]);
        // Written after every comment, the reply still comes with the comment it answers.
        $reply = $this->replyAt($comments[1], $owner, 30);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?before=' . self::cursor($comments[3]));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/user/profile-post-comments/' . $comments[1]->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $comments[1]->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => 'Commentaire 1',
                    'creation_datetime' => '2026-11-02T10:01:00+00:00',
                    'can_delete' => false,
                    'cursor' => self::cursor($comments[1]),
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $reply->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $reply->id,
                    'parent_id' => $comments[1]->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => 'Réponse 30',
                    'creation_datetime' => '2026-11-02T10:30:00+00:00',
                    'can_delete' => false,
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?before=' . self::cursor($comments[3]),
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_comments_written_in_the_same_second_are_paged_by_id(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $sameSecond = new \DateTimeImmutable('2026-11-02T10:00:00+00:00');
        $comments = [];
        foreach (['Un', 'Deux', 'Trois'] as $content) {
            $comments[] = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $owner, 'content' => $content, 'creationDatetime' => $sameSecond]);
        }
        // The server orders a shared second by id, newest first.
        usort($comments, static fn (ProfilePostComment $a, ProfilePostComment $b): int => strcmp((string) $b->id, (string) $a->id));
        [, $middle, $oldest] = $comments;

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?before=' . self::cursor($middle));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/profile-post-comments/' . $oldest->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $oldest->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => $oldest->content,
                    'creation_datetime' => '2026-11-02T10:00:00+00:00',
                    'can_delete' => false,
                    'cursor' => self::cursor($oldest),
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?before=' . self::cursor($middle),
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedCursors(): iterable
    {
        yield 'a trailing newline' => ['recent_1793613600_01a1b220-df08-7910-9e2a-d78c147e10d0%0A'];
        yield 'a null byte' => ['recent_1793613600_01a1b220-df08-7910-9e2a-d78c147e10d0%00'];
        yield 'an unknown order' => ['popular_1793613600_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'a wall cursor' => ['post_1793613600_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'a bare comment id' => ['01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'an overlong timestamp' => ['recent_179361360000_01a1b220-df08-7910-9e2a-d78c147e10d0'];
    }

    #[DataProvider('malformedCursors')]
    public function test_a_malformed_cursor_is_refused(string $cursor): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?before=' . $cursor);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'before',
                    'message' => "La suite des commentaires demandée n'est pas valide",
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => "before: La suite des commentaires demandée n'est pas valide",
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
            'description' => "before: La suite des commentaires demandée n'est pas valide",
        ]);
    }

    public function test_a_cursor_given_as_a_list_is_refused(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?before[]=x');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'before',
                    'message' => "La suite des commentaires demandée n'est pas valide",
                    'code' => 'ba785a8c-82cb-4283-967c-3cf342181b40',
                ],
            ],
            'detail' => "before: La suite des commentaires demandée n'est pas valide",
            'type' => '/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            'title' => 'An error occurred',
            'description' => "before: La suite des commentaires demandée n'est pas valide",
        ]);
    }

    public function test_around_a_comment_further_down_the_first_page_reaches_down_to_it(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $comments = $this->commentsAt($post, $owner, range(1, 25));

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?around=' . $comments[3]->id);

        $this->assertResponseIsSuccessful();
        // Every comment from the newest down to minute 3, so `before` goes on from its cursor.
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 23,
            'member' => array_map(static fn (int $minute): array => [
                '@id' => '/api/user/profile-post-comments/' . $comments[$minute]->id,
                '@type' => 'ProfilePostComment',
                'id' => $comments[$minute]->id,
                'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                'content' => 'Commentaire ' . $minute,
                'creation_datetime' => sprintf('2026-11-02T10:%02d:00+00:00', $minute),
                'can_delete' => false,
                'cursor' => self::cursor($comments[$minute]),
            ], range(25, 3)),
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?around=' . $comments[3]->id,
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_around_a_reply_reaches_down_to_the_comment_it_answers(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $comments = $this->commentsAt($post, $owner, range(1, 22));
        $reply = $this->replyAt($comments[1], $owner, 30);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?around=' . $reply->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 23,
            'member' => [
                ...array_map(static fn (int $minute): array => [
                    '@id' => '/api/user/profile-post-comments/' . $comments[$minute]->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $comments[$minute]->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => 'Commentaire ' . $minute,
                    'creation_datetime' => sprintf('2026-11-02T10:%02d:00+00:00', $minute),
                    'can_delete' => false,
                    'cursor' => self::cursor($comments[$minute]),
                ], range(22, 1)),
                [
                    '@id' => '/api/user/profile-post-comments/' . $reply->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $reply->id,
                    'parent_id' => $comments[1]->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => 'Réponse 30',
                    'creation_datetime' => '2026-11-02T10:30:00+00:00',
                    'can_delete' => false,
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?around=' . $reply->id,
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_around_a_hidden_comment_opens_the_first_page(): void
    {
        $viewer = $this->member('chloe');
        $owner = $this->member('julien');
        $blocked = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $blocked, 'blocked' => $viewer]);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $comments = $this->commentsAt($post, $owner, range(2, 22));
        $hidden = $this->commentsAt($post, $blocked, [1])[1];

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?around=' . $hidden->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 20,
            'member' => array_map(static fn (int $minute): array => [
                '@id' => '/api/user/profile-post-comments/' . $comments[$minute]->id,
                '@type' => 'ProfilePostComment',
                'id' => $comments[$minute]->id,
                'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                'content' => 'Commentaire ' . $minute,
                'creation_datetime' => sprintf('2026-11-02T10:%02d:00+00:00', $minute),
                'can_delete' => false,
                'cursor' => self::cursor($comments[$minute]),
            ], range(22, 3)),
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?around=' . $hidden->id,
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_around_a_comment_of_another_post_opens_the_first_page(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $comments = $this->commentsAt($post, $owner, range(1, 21));
        $elsewhere = $this->commentsAt(ProfilePostFactory::new()->create(['author' => $owner]), $owner, [0])[0];

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?around=' . $elsewhere->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 20,
            'member' => array_map(static fn (int $minute): array => [
                '@id' => '/api/user/profile-post-comments/' . $comments[$minute]->id,
                '@type' => 'ProfilePostComment',
                'id' => $comments[$minute]->id,
                'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                'content' => 'Commentaire ' . $minute,
                'creation_datetime' => sprintf('2026-11-02T10:%02d:00+00:00', $minute),
                'can_delete' => false,
                'cursor' => self::cursor($comments[$minute]),
            ], range(21, 2)),
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?around=' . $elsewhere->id,
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_around_a_comment_past_the_limit_opens_the_first_page(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        // 101 comments from the newest down to the oldest, one more than a page reaching down may hold.
        $comments = $this->commentsAt($post, $owner, range(0, 100));

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?around=' . $comments[0]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 20,
            'member' => array_map(static fn (int $minute): array => [
                '@id' => '/api/user/profile-post-comments/' . $comments[$minute]->id,
                '@type' => 'ProfilePostComment',
                'id' => $comments[$minute]->id,
                'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                'content' => 'Commentaire ' . $minute,
                'creation_datetime' => $comments[$minute]->creationDatetime->format(\DATE_ATOM),
                'can_delete' => false,
                'cursor' => self::cursor($comments[$minute]),
            ], range(100, 81)),
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?around=' . $comments[0]->id,
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedCommentIds(): iterable
    {
        yield 'a trailing newline' => ['01a1b220-df08-7910-9e2a-d78c147e10d0%0A'];
        yield 'a null byte' => ['01a1b220-df08-7910-9e2a-d78c147e10d0%00'];
        yield 'a cursor' => ['recent_1793613600_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'upper case' => ['01A1B220-DF08-7910-9E2A-D78C147E10D0'];
    }

    #[DataProvider('malformedCommentIds')]
    public function test_a_malformed_comment_id_is_refused(string $commentId): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?around=' . $commentId);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'around',
                    'message' => "Le commentaire demandé n'est pas valide",
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => "around: Le commentaire demandé n'est pas valide",
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
            'description' => "around: Le commentaire demandé n'est pas valide",
        ]);
    }

    public function test_a_comment_id_given_as_a_list_is_refused(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?around[]=x');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'around',
                    'message' => "Le commentaire demandé n'est pas valide",
                    'code' => 'ba785a8c-82cb-4283-967c-3cf342181b40',
                ],
            ],
            'detail' => "around: Le commentaire demandé n'est pas valide",
            'type' => '/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            'title' => 'An error occurred',
            'description' => "around: Le commentaire demandé n'est pas valide",
        ]);
    }

    private static function cursor(ProfilePostComment $comment): string
    {
        return 'recent_' . $comment->creationDatetime->getTimestamp() . '_' . $comment->id;
    }

    /**
     * One top-level comment per minute after 10:00, « Commentaire {minute} ».
     *
     * @param array<int> $minutes
     *
     * @return array<int, ProfilePostComment> by minute
     */
    private function commentsAt(ProfilePost $post, User $author, array $minutes): array
    {
        $comments = [];
        foreach ($minutes as $minute) {
            $comments[$minute] = ProfilePostCommentFactory::new()->create([
                'post' => $post,
                'author' => $author,
                'content' => 'Commentaire ' . $minute,
                'creationDatetime' => new \DateTimeImmutable('2026-11-02T10:00:00+00:00')->modify(sprintf('+%d minutes', $minute)),
            ]);
        }

        return $comments;
    }

    private function replyAt(ProfilePostComment $parent, User $author, int $minute): ProfilePostComment
    {
        return ProfilePostCommentFactory::new()->create([
            'post' => $parent->post,
            'author' => $author,
            'parent' => $parent,
            'content' => 'Réponse ' . $minute,
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T10:00:00+00:00')->modify(sprintf('+%d minutes', $minute)),
        ]);
    }

    private function member(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }
}
