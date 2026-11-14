<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentLikeRepository;
use App\Service\Procedure\User\DeleteAccountProcedure;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Profile\ProfilePostCommentFactory;
use App\Tests\Factory\Profile\ProfilePostCommentLikeFactory;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** Likes on a wall post comment or reply, and the « Plus appréciés » order they feed (#1331). */
#[ResetDatabase]
class ProfilePostCommentLikeTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_like_a_comment(): void
    {
        $comment = $this->commentOn($this->postOf($this->member('julien')), $this->member('marc'), 1);
        $this->likedBy($comment, [$this->member('lea')]);
        $chloe = $this->member('chloe');

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-post-comments/' . $comment->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostCommentLike',
            '@id' => '/api/profile_post_comment_likes/' . $comment->id,
            '@type' => 'ProfilePostCommentLike',
            'comment_id' => $comment->id,
            'like_count' => 2,
            'has_liked' => true,
        ]);
        $this->assertSame(['stored' => 2, 'likes' => 2], $this->likesOf($comment));
    }

    public function test_liking_twice_keeps_one_like_and_counts_it_once(): void
    {
        $comment = $this->commentOn($this->postOf($this->member('julien')), $this->member('marc'), 1);
        $chloe = $this->member('chloe');
        $this->likedBy($comment, [$chloe]);

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-post-comments/' . $comment->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostCommentLike',
            '@id' => '/api/profile_post_comment_likes/' . $comment->id,
            '@type' => 'ProfilePostCommentLike',
            'comment_id' => $comment->id,
            'like_count' => 1,
            'has_liked' => true,
        ]);
        $this->assertSame(['stored' => 1, 'likes' => 1], $this->likesOf($comment));
    }

    public function test_the_like_count_answered_leaves_out_anyone_blocked_either_way(): void
    {
        $comment = $this->commentOn($this->postOf($this->member('julien')), $this->member('marc'), 1);
        $chloe = $this->member('chloe');
        $blocked = $this->member('paul');
        UserBlockFactory::new()->create(['blocker' => $blocked, 'blocked' => $chloe]);
        $this->likedBy($comment, [$blocked]);

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-post-comments/' . $comment->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        // The stored count, which the sort reads, holds both likes.
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostCommentLike',
            '@id' => '/api/profile_post_comment_likes/' . $comment->id,
            '@type' => 'ProfilePostCommentLike',
            'comment_id' => $comment->id,
            'like_count' => 1,
            'has_liked' => true,
        ]);
        $this->assertSame(['stored' => 2, 'likes' => 2], $this->likesOf($comment));
    }

    public function test_take_a_like_on_a_reply_back(): void
    {
        $post = $this->postOf($this->member('julien'));
        $parent = $this->commentOn($post, $this->member('marc'), 1);
        $reply = $this->commentOn($post, $this->member('lea'), 2, $parent);
        $chloe = $this->member('chloe');
        $this->likedBy($reply, [$chloe, $this->member('paul')]);

        $this->client->loginUser($chloe);
        $this->client->request('DELETE', '/api/user/profile-post-comments/' . $reply->id . '/like');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(['stored' => 1, 'likes' => 1], $this->likesOf($reply));
    }

    public function test_a_like_not_held_cannot_be_taken_back(): void
    {
        $comment = $this->commentOn($this->postOf($this->member('julien')), $this->member('marc'), 1);
        $this->likedBy($comment, [$this->member('lea')]);

        $this->client->loginUser($this->member('chloe'));
        $this->client->request('DELETE', '/api/user/profile-post-comments/' . $comment->id . '/like');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => "Tu n'aimes pas ce commentaire",
            'description' => "Tu n'aimes pas ce commentaire",
        ]);
        $this->assertSame(['stored' => 1, 'likes' => 1], $this->likesOf($comment));
    }

    public function test_a_comment_hidden_by_a_block_takes_no_like(): void
    {
        $chloe = $this->member('chloe');
        $marc = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $marc, 'blocked' => $chloe]);
        $comment = $this->commentOn($this->postOf($this->member('julien')), $marc, 1);

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-post-comments/' . $comment->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertCommentNotFound();
        $this->assertSame(['stored' => 0, 'likes' => 0], $this->likesOf($comment));
    }

    public function test_a_reply_under_a_hidden_comment_takes_no_like(): void
    {
        $chloe = $this->member('chloe');
        $marc = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $chloe, 'blocked' => $marc]);
        $post = $this->postOf($this->member('julien'));
        $reply = $this->commentOn($post, $this->member('lea'), 2, $this->commentOn($post, $marc, 1));

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-post-comments/' . $reply->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertCommentNotFound();
    }

    public function test_an_unknown_comment_takes_no_like(): void
    {
        $this->client->loginUser($this->member('chloe'));
        $this->client->jsonRequest('POST', '/api/user/profile-post-comments/0195f3a0-0000-7000-8000-000000000000/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertCommentNotFound();
    }

    public function test_a_wall_hidden_by_a_block_takes_no_like_on_its_comments(): void
    {
        $owner = $this->member('julien');
        $chloe = $this->member('chloe');
        UserBlockFactory::new()->create(['blocker' => $owner, 'blocked' => $chloe]);
        $comment = $this->commentOn($this->postOf($owner), $this->member('marc'), 1);

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-post-comments/' . $comment->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Post introuvable',
            'description' => 'Post introuvable',
        ]);
    }

    public function test_a_visitor_cannot_like_a_comment(): void
    {
        $comment = $this->commentOn($this->postOf($this->member('julien')), $this->member('marc'), 1);

        $this->client->jsonRequest('POST', '/api/user/profile-post-comments/' . $comment->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_each_comment_and_reply_shows_its_likes_without_anyone_blocked_either_way(): void
    {
        $chloe = $this->member('chloe');
        $blocked = $this->member('paul');
        UserBlockFactory::new()->create(['blocker' => $chloe, 'blocked' => $blocked]);
        $post = $this->postOf($this->member('julien'));
        $comment = $this->commentOn($post, $this->member('marc'), 1);
        $reply = $this->commentOn($post, $this->member('lea'), 2, $comment);
        $this->likedBy($comment, [$chloe, $this->member('tom'), $blocked]);
        $this->likedBy($reply, [$this->member('zoe')]);

        $this->client->loginUser($chloe);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/user/profile-post-comments/' . $comment->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $comment->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => 'Commentaire 1',
                    'creation_datetime' => '2026-11-02T10:01:00+00:00',
                    'can_delete' => false,
                    'like_count' => 2,
                    'has_liked' => true,
                    'cursor' => 'recent_' . $comment->creationDatetime->getTimestamp() . '_' . $comment->id,
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $reply->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $reply->id,
                    'parent_id' => $comment->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'lea', 'display_name' => 'lea'],
                    'content' => 'Commentaire 2',
                    'creation_datetime' => '2026-11-02T10:02:00+00:00',
                    'can_delete' => false,
                    'like_count' => 1,
                    'has_liked' => false,
                ],
            ],
        ]);
    }

    public function test_the_most_liked_come_first_then_the_newest(): void
    {
        $post = $this->postOf($this->member('julien'));
        $marc = $this->member('marc');
        [$tom, $zoe, $lea] = [$this->member('tom'), $this->member('zoe'), $this->member('lea')];
        $popular = $this->commentOn($post, $marc, 1);
        $this->likedBy($popular, [$tom, $zoe]);
        $older = $this->commentOn($post, $marc, 2);
        $this->likedBy($older, [$lea]);
        $newer = $this->commentOn($post, $marc, 3);
        $this->likedBy($newer, [$tom]);
        $unliked = $this->commentOn($post, $marc, 4);
        // A reply stays under its comment whatever its own likes.
        $reply = $this->commentOn($post, $marc, 5, $older);
        $this->likedBy($reply, [$tom, $zoe, $lea]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?order=liked');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 5,
            'member' => [
                [
                    '@id' => '/api/user/profile-post-comments/' . $popular->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $popular->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => 'Commentaire 1',
                    'creation_datetime' => '2026-11-02T10:01:00+00:00',
                    'can_delete' => false,
                    'like_count' => 2,
                    'has_liked' => false,
                    'cursor' => self::likedCursor($popular),
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $newer->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $newer->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => 'Commentaire 3',
                    'creation_datetime' => '2026-11-02T10:03:00+00:00',
                    'can_delete' => false,
                    'like_count' => 1,
                    'has_liked' => false,
                    'cursor' => self::likedCursor($newer),
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $older->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $older->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => 'Commentaire 2',
                    'creation_datetime' => '2026-11-02T10:02:00+00:00',
                    'can_delete' => false,
                    'like_count' => 1,
                    'has_liked' => false,
                    'cursor' => self::likedCursor($older),
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $reply->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $reply->id,
                    'parent_id' => $older->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => 'Commentaire 5',
                    'creation_datetime' => '2026-11-02T10:05:00+00:00',
                    'can_delete' => false,
                    'like_count' => 3,
                    'has_liked' => false,
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $unliked->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $unliked->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => 'Commentaire 4',
                    'creation_datetime' => '2026-11-02T10:04:00+00:00',
                    'can_delete' => false,
                    'like_count' => 0,
                    'has_liked' => false,
                    'cursor' => self::likedCursor($unliked),
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?order=liked',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_the_most_liked_order_pages_across_equal_counts_and_a_shared_second(): void
    {
        $post = $this->postOf($this->member('julien'));
        $marc = $this->member('marc');
        $tom = $this->member('tom');
        $sameSecond = [];
        foreach (['Un', 'Deux'] as $content) {
            $comment = ProfilePostCommentFactory::new()->create([
                'post' => $post,
                'author' => $marc,
                'content' => $content,
                'creationDatetime' => new \DateTimeImmutable('2026-11-02T10:03:00+00:00'),
            ]);
            $this->likedBy($comment, [$tom]);
            $sameSecond[] = $comment;
        }
        // Among equal counts and one second, the higher id comes first.
        usort($sameSecond, static fn (ProfilePostComment $a, ProfilePostComment $b): int => strcmp((string) $b->id, (string) $a->id));
        [$first, $second] = $sameSecond;
        $olderEqual = $this->commentOn($post, $marc, 1);
        $this->likedBy($olderEqual, [$tom]);
        $unliked = $this->commentOn($post, $marc, 9);
        $this->likedBy($this->commentOn($post, $marc, 2), [$tom, $this->member('zoe')]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?order=liked&before=' . self::likedCursor($first));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                [
                    '@id' => '/api/user/profile-post-comments/' . $second->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $second->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => $second->content,
                    'creation_datetime' => '2026-11-02T10:03:00+00:00',
                    'can_delete' => false,
                    'like_count' => 1,
                    'has_liked' => false,
                    'cursor' => self::likedCursor($second),
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $olderEqual->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $olderEqual->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => 'Commentaire 1',
                    'creation_datetime' => '2026-11-02T10:01:00+00:00',
                    'can_delete' => false,
                    'like_count' => 1,
                    'has_liked' => false,
                    'cursor' => self::likedCursor($olderEqual),
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $unliked->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $unliked->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => 'Commentaire 9',
                    'creation_datetime' => '2026-11-02T10:09:00+00:00',
                    'can_delete' => false,
                    'like_count' => 0,
                    'has_liked' => false,
                    'cursor' => self::likedCursor($unliked),
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?before=' . self::likedCursor($first) . '&order=liked',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_around_in_the_most_liked_order_reaches_down_to_the_comment(): void
    {
        $post = $this->postOf($this->member('julien'));
        $marc = $this->member('marc');
        $comments = [];
        foreach (range(1, 22) as $minute) {
            $comments[$minute] = $this->commentOn($post, $marc, $minute);
        }
        $this->likedBy($comments[1], [$this->member('tom')]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments?order=liked&around=' . $comments[2]->id);

        $this->assertResponseIsSuccessful();
        // The liked one first, then the newest down to minute 2, the last of the unliked ones.
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 22,
            'member' => array_map(static fn (int $minute): array => [
                '@id' => '/api/user/profile-post-comments/' . $comments[$minute]->id,
                '@type' => 'ProfilePostComment',
                'id' => $comments[$minute]->id,
                'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                'content' => 'Commentaire ' . $minute,
                'creation_datetime' => sprintf('2026-11-02T10:%02d:00+00:00', $minute),
                'can_delete' => false,
                'like_count' => $minute === 1 ? 1 : 0,
                'has_liked' => false,
                'cursor' => self::likedCursor($comments[$minute]),
            ], [1, ...range(22, 2)]),
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/comments?around=' . $comments[2]->id . '&order=liked',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_deleting_the_account_takes_its_comment_likes_out_of_the_counts(): void
    {
        $leaving = $this->member('chloe');
        $lea = $this->member('lea');
        $othersPost = $this->postOf($this->member('julien'));
        $marc = $this->member('marc');
        $sharedLike = $this->commentOn($othersPost, $marc, 1);
        $this->likedBy($sharedLike, [$leaving, $lea]);
        $onlyLike = $this->commentOn($othersPost, $marc, 2);
        $this->likedBy($onlyLike, [$leaving]);
        // A comment on their own post goes with the post, its likes with it.
        $onOwnWall = $this->commentOn($this->postOf($leaving), $lea, 3);
        $this->likedBy($onOwnWall, [$lea]);
        $ids = [(string) $sharedLike->id, (string) $onlyLike->id, (string) $onOwnWall->id];
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();

        $user = $entityManager->find(User::class, $leaving->id);
        $this->assertInstanceOf(User::class, $user);
        self::getContainer()->get(DeleteAccountProcedure::class)->process($user);

        $this->assertSame([$ids[0] => 1], self::getContainer()->get(ProfilePostCommentLikeRepository::class)->countByCommentIds($ids, []));
        $this->assertSame(['stored' => 1, 'likes' => 1], $this->likesOf($sharedLike));
        $this->assertSame(['stored' => 0, 'likes' => 0], $this->likesOf($onlyLike));
    }

    private function assertCommentNotFound(): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Commentaire introuvable',
            'description' => 'Commentaire introuvable',
        ]);
    }

    private static function likedCursor(ProfilePostComment $comment): string
    {
        return 'liked_' . $comment->likeCount . '_' . $comment->creationDatetime->getTimestamp() . '_' . $comment->id;
    }

    /**
     * The comment's stored count and its like rows, read back from the database.
     *
     * @return array{stored: int, likes: int}
     */
    private function likesOf(ProfilePostComment $comment): array
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $stored = $entityManager->find(ProfilePostComment::class, $comment->id);
        $this->assertInstanceOf(ProfilePostComment::class, $stored);
        $likes = self::getContainer()->get(ProfilePostCommentLikeRepository::class)->countByCommentIds([(string) $comment->id], []);

        return ['stored' => $stored->likeCount, 'likes' => $likes[(string) $comment->id] ?? 0];
    }

    /**
     * Likes seeded as the API leaves them: a row each, and the stored count in step.
     *
     * @param list<User> $users
     */
    private function likedBy(ProfilePostComment $comment, array $users): void
    {
        foreach ($users as $user) {
            ProfilePostCommentLikeFactory::new()->create(['comment' => $comment, 'user' => $user]);
        }
        $comment->likeCount += count($users);
        \Zenstruck\Foundry\Persistence\save($comment);
    }

    private function commentOn(ProfilePost $post, User $author, int $minute, ?ProfilePostComment $parent = null): ProfilePostComment
    {
        return ProfilePostCommentFactory::new()->create([
            'post' => $post,
            'author' => $author,
            'parent' => $parent,
            'content' => 'Commentaire ' . $minute,
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T10:00:00+00:00')->modify(sprintf('+%d minutes', $minute)),
        ]);
    }

    private function postOf(User $author): ProfilePost
    {
        return ProfilePostFactory::new()->create(['author' => $author]);
    }

    private function member(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }
}
