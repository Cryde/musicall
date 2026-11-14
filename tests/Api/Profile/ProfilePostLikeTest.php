<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Profile\ProfilePostLike;
use App\Entity\User;
use App\Repository\Profile\ProfilePostLikeRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Profile\ProfilePostCommentFactory;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\Profile\ProfilePostLikeFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** Likes on a wall post, who gave them a page at a time (#1331), and what the wall shows of likes and comments (#1316). */
#[ResetDatabase]
class ProfilePostLikeTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_like_a_post(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        ProfilePostLikeFactory::new()->create(['post' => $post, 'user' => $this->member('marc')]);
        $chloe = $this->member('chloe');

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostLike',
            '@id' => '/api/profile_post_likes/' . $post->id,
            '@type' => 'ProfilePostLike',
            'post_id' => $post->id,
            'like_count' => 2,
            'has_liked' => true,
        ]);
    }

    public function test_liking_twice_keeps_one_like(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $chloe = $this->member('chloe');
        ProfilePostLikeFactory::new()->create(['post' => $post, 'user' => $chloe]);

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostLike',
            '@id' => '/api/profile_post_likes/' . $post->id,
            '@type' => 'ProfilePostLike',
            'post_id' => $post->id,
            'like_count' => 1,
            'has_liked' => true,
        ]);
    }

    public function test_take_a_like_back(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $chloe = $this->member('chloe');
        ProfilePostLikeFactory::new()->create(['post' => $post, 'user' => $chloe]);
        $postId = (string) $post->id;

        $this->client->loginUser($chloe);
        $this->client->request('DELETE', '/api/user/profile-posts/' . $postId . '/like');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([], self::getContainer()->get(ProfilePostLikeRepository::class)->countByPostIds([$postId]));
    }

    public function test_a_like_not_held_cannot_be_taken_back(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->loginUser($this->member('chloe'));
        $this->client->request('DELETE', '/api/user/profile-posts/' . $post->id . '/like');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => "Tu n'aimes pas ce post",
            'description' => "Tu n'aimes pas ce post",
        ]);
    }

    public function test_a_wall_hidden_by_a_block_takes_no_like(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        UserBlockFactory::new()->create(['blocker' => $owner, 'blocked' => $viewer]);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);

        $this->client->loginUser($viewer);
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/like', [], [
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

    public function test_a_visitor_cannot_like(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/like', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_who_liked_newest_first_without_anyone_blocked_either_way(): void
    {
        $viewer = $this->member('chloe');
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $private = $this->member('paul', isPublic: false);
        $private->profile->displayName = 'Paul Martin';
        \Zenstruck\Foundry\Persistence\save($private);
        $blocked = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $blocked, 'blocked' => $viewer]);
        $leaLike = ProfilePostLikeFactory::new()->create([
            'post' => $post,
            'user' => $this->member('lea.dupont'),
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T10:00:00+00:00'),
        ]);
        $paulLike = ProfilePostLikeFactory::new()->create([
            'post' => $post,
            'user' => $private,
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T11:00:00+00:00'),
        ]);
        ProfilePostLikeFactory::new()->create([
            'post' => $post,
            'user' => $blocked,
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T12:00:00+00:00'),
        ]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/likes');

        $this->assertResponseIsSuccessful();
        // A private profile keeps its display name to itself: its username stands in. A dot in a username
        // still makes an address.
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostLiker',
            '@id' => '/api/user/profile-posts/' . $post->id . '/likes',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/profile_post_likers/paul',
                    '@type' => 'ProfilePostLiker',
                    'username' => 'paul',
                    'display_name' => 'paul',
                    'cursor' => $paulLike->creationDatetime->getTimestamp() . '_' . $paulLike->id,
                ],
                [
                    '@id' => '/api/profile_post_likers/lea.dupont',
                    '@type' => 'ProfilePostLiker',
                    'username' => 'lea.dupont',
                    'display_name' => 'lea.dupont',
                    'cursor' => $leaLike->creationDatetime->getTimestamp() . '_' . $leaLike->id,
                ],
            ],
        ]);
    }

    public function test_likers_come_twenty_at_a_time(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $likes = [];
        foreach (range(1, 21) as $minute) {
            $likes[$minute] = ProfilePostLikeFactory::new()->create([
                'post' => $post,
                'user' => $this->member('fan' . $minute),
                'creationDatetime' => new \DateTimeImmutable(sprintf('2026-11-02T10:%02d:00+00:00', $minute)),
            ]);
        }

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/likes');

        $this->assertResponseIsSuccessful();
        // Newest first: minutes 21 down to 2, minute 1 waits for the next page.
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostLiker',
            '@id' => '/api/user/profile-posts/' . $post->id . '/likes',
            '@type' => 'Collection',
            'totalItems' => 20,
            'member' => array_map(static fn (int $minute): array => [
                '@id' => '/api/profile_post_likers/fan' . $minute,
                '@type' => 'ProfilePostLiker',
                'username' => 'fan' . $minute,
                'display_name' => 'fan' . $minute,
                'cursor' => $likes[$minute]->creationDatetime->getTimestamp() . '_' . $likes[$minute]->id,
            ], range(21, 2)),
        ]);
    }

    public function test_the_next_likers_start_after_the_cursor_without_anyone_blocked_either_way(): void
    {
        $viewer = $this->member('chloe');
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $blocked = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blocked]);
        $sameSecond = new \DateTimeImmutable('2026-11-02T10:00:00+00:00');
        $likes = [];
        foreach (['lea', 'paul', 'tom'] as $username) {
            $likes[] = ProfilePostLikeFactory::new()->create(['post' => $post, 'user' => $this->member($username), 'creationDatetime' => $sameSecond]);
        }
        ProfilePostLikeFactory::new()->create(['post' => $post, 'user' => $blocked, 'creationDatetime' => new \DateTimeImmutable('2026-11-02T09:00:00+00:00')]);
        // The server orders a shared second by id, newest first.
        usort($likes, static fn (ProfilePostLike $a, ProfilePostLike $b): int => strcmp((string) $b->id, (string) $a->id));
        [, $middle, $oldest] = $likes;
        $before = $middle->creationDatetime->getTimestamp() . '_' . $middle->id;

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/likes?before=' . $before);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostLiker',
            '@id' => '/api/user/profile-posts/' . $post->id . '/likes',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/profile_post_likers/' . $oldest->user->username,
                    '@type' => 'ProfilePostLiker',
                    'username' => $oldest->user->username,
                    'display_name' => $oldest->user->username,
                    'cursor' => $oldest->creationDatetime->getTimestamp() . '_' . $oldest->id,
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile-posts/' . $post->id . '/likes?before=' . $before,
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedCursors(): iterable
    {
        yield 'a trailing newline' => ['1793613600_01a1b220-df08-7910-9e2a-d78c147e10d0%0A'];
        yield 'a null byte' => ['1793613600_01a1b220-df08-7910-9e2a-d78c147e10d0%00'];
        yield 'a comment cursor' => ['recent_1793613600_01a1b220-df08-7910-9e2a-d78c147e10d0'];
        yield 'a username' => ['chloe'];
    }

    #[DataProvider('malformedCursors')]
    public function test_a_malformed_cursor_is_refused(string $cursor): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/likes?before=' . $cursor);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'before',
                    'message' => "La suite de la liste demandée n'est pas valide",
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => "before: La suite de la liste demandée n'est pas valide",
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
            'description' => "before: La suite de la liste demandée n'est pas valide",
        ]);
    }

    public function test_a_cursor_given_as_a_list_is_refused(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/likes?before[]=x');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'before',
                    'message' => "La suite de la liste demandée n'est pas valide",
                    'code' => 'ba785a8c-82cb-4283-967c-3cf342181b40',
                ],
            ],
            'detail' => "before: La suite de la liste demandée n'est pas valide",
            'type' => '/validation_errors/ba785a8c-82cb-4283-967c-3cf342181b40',
            'title' => 'An error occurred',
            'description' => "before: La suite de la liste demandée n'est pas valide",
        ]);
    }

    public function test_a_private_wall_has_private_likes(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien', isPublic: false)]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/likes');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/profile-private',
            'detail' => 'Ce profil est privé',
            'description' => 'Ce profil est privé',
        ]);
    }

    public function test_the_wall_counts_likes_and_the_comments_the_viewer_can_read(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        $blocked = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blocked]);
        $post = ProfilePostFactory::new()->create(['author' => $owner, 'content' => 'Nouveau clip']);
        ProfilePostLikeFactory::new()->create(['post' => $post, 'user' => $viewer]);
        ProfilePostLikeFactory::new()->create(['post' => $post, 'user' => $blocked]);
        ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $owner]);
        $hidden = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $blocked]);
        ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $owner, 'parent' => $hidden]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        // Likes and comments are counted as the viewer reads them: without anyone blocked either way.
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/profile_wall_items/post_' . $post->creationDatetime->getTimestamp() . '_' . $post->id,
                    '@type' => 'ProfileWallItem',
                    'cursor' => 'post_' . $post->creationDatetime->getTimestamp() . '_' . $post->id,
                    'type' => 'post',
                    'date' => '2026-11-01T10:00:00+00:00',
                    'post' => [
                        '@type' => 'ProfilePost',
                        'id' => $post->id,
                        'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                        'content' => 'Nouveau clip',
                        'photos' => [],
                        'is_pinned' => false,
                        'creation_datetime' => '2026-11-01T10:00:00+00:00',
                        'can_edit' => false,
                        'like_count' => 1,
                        'has_liked' => true,
                        'comment_count' => 1,
                    ],
                ],
            ],
        ]);
    }

    public function test_a_wall_hidden_by_a_block_takes_no_unlike(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        ProfilePostLikeFactory::new()->create(['post' => $post, 'user' => $viewer]);
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $owner]);

        $this->client->loginUser($viewer);
        $this->client->request('DELETE', '/api/user/profile-posts/' . $post->id . '/like');

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

    private function member(string $username, bool $isPublic = true): User
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
        $user->profile->isPublic = $isPublic;
        \Zenstruck\Foundry\Persistence\save($user);

        return $user;
    }
}
