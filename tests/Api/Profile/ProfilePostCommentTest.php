<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Profile\ProfilePostComment;
use App\Entity\User;
use App\Repository\Profile\ProfilePostCommentRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Profile\ProfilePostCommentFactory;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** Comments and replies on a wall post (#1316). */
#[ResetDatabase]
class ProfilePostCommentTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_post_s_comments_come_oldest_first_with_their_replies(): void
    {
        $owner = $this->member('julien');
        $chloe = $this->member('chloe');
        $marc = $this->member('marc');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $first = ProfilePostCommentFactory::new()->create([
            'post' => $post,
            'author' => $chloe,
            'content' => 'La MercuryX, sans hésiter',
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T10:00:00+00:00'),
        ]);
        $reply = ProfilePostCommentFactory::new()->create([
            'post' => $post,
            'author' => $owner,
            'parent' => $first,
            'content' => 'Merci !',
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T11:00:00+00:00'),
        ]);
        $second = ProfilePostCommentFactory::new()->create([
            'post' => $post,
            'author' => $marc,
            'content' => 'La BigSky pour moi',
            'creationDatetime' => new \DateTimeImmutable('2026-11-02T12:00:00+00:00'),
        ]);

        // Chloé may delete her own comment only; the wall is Julien's.
        $this->client->loginUser($chloe);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                [
                    '@id' => '/api/user/profile-post-comments/' . $first->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $first->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'chloe', 'display_name' => 'chloe'],
                    'content' => 'La MercuryX, sans hésiter',
                    'creation_datetime' => '2026-11-02T10:00:00+00:00',
                    'can_delete' => true,
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $reply->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $reply->id,
                    'parent_id' => $first->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => 'Merci !',
                    'creation_datetime' => '2026-11-02T11:00:00+00:00',
                    'can_delete' => false,
                ],
                [
                    '@id' => '/api/user/profile-post-comments/' . $second->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $second->id,
                    'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'marc', 'display_name' => 'marc'],
                    'content' => 'La BigSky pour moi',
                    'creation_datetime' => '2026-11-02T12:00:00+00:00',
                    'can_delete' => false,
                ],
            ],
        ]);
    }

    public function test_the_wall_owner_may_delete_every_comment(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $comment = ProfilePostCommentFactory::new()->create([
            'post' => $post,
            'author' => $this->member('chloe'),
            'content' => 'Bravo',
        ]);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/api/user/profile-post-comments/' . $comment->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-post-comments/' . $comment->id,
            '@type' => 'ProfilePostComment',
            'id' => $comment->id,
            'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'chloe', 'display_name' => 'chloe'],
            'content' => 'Bravo',
            'creation_datetime' => '2026-11-02T10:00:00+00:00',
            'can_delete' => true,
        ]);
    }

    public function test_one_comment_on_a_private_wall_is_private(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien', isPublic: false)]);
        $comment = ProfilePostCommentFactory::new()->create(['post' => $post]);

        $this->client->request('GET', '/api/user/profile-post-comments/' . $comment->id);

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

    public function test_one_comment_on_a_wall_hidden_by_a_block_is_not_found(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        UserBlockFactory::new()->create(['blocker' => $owner, 'blocked' => $viewer]);
        $comment = ProfilePostCommentFactory::new()->create(['post' => ProfilePostFactory::new()->create(['author' => $owner])]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile-post-comments/' . $comment->id);

        $this->assertPostNotFound();
    }

    public function test_one_reply_under_a_hidden_comment_is_not_found(): void
    {
        $viewer = $this->member('chloe');
        $marc = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $marc]);
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $hidden = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $marc]);
        $reply = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $this->member('lea'), 'parent' => $hidden]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile-post-comments/' . $reply->id);

        $this->assertCommentNotFound();
    }

    public function test_an_unknown_comment_is_not_found(): void
    {
        $this->client->request('GET', '/api/user/profile-post-comments/0195f3a0-0000-7000-8000-000000000000');

        $this->assertCommentNotFound();
    }

    public function test_a_closed_account_s_comment_stays_with_a_deleted_author(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $gone = $this->member('deleted_123');
        $gone->deletionDatetime = new \DateTimeImmutable('2026-11-03T08:00:00+00:00');
        \Zenstruck\Foundry\Persistence\save($gone);
        $comment = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $gone, 'content' => 'Salut']);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/profile-post-comments/' . $comment->id,
                    '@type' => 'ProfilePostComment',
                    'id' => $comment->id,
                    'author' => [
                        '@type' => 'ProfilePostCommentAuthor',
                        'username' => 'deleted_123',
                        'display_name' => 'Utilisateur supprimé',
                        'deletion_datetime' => '2026-11-03T08:00:00+00:00',
                    ],
                    'content' => 'Salut',
                    'creation_datetime' => '2026-11-02T10:00:00+00:00',
                    'can_delete' => false,
                ],
            ],
        ]);
    }

    public function test_the_comments_of_someone_blocked_either_way_are_left_out(): void
    {
        $viewer = $this->member('chloe');
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $blocked = $this->member('marc');
        $blocker = $this->member('paul');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blocked]);
        UserBlockFactory::new()->create(['blocker' => $blocker, 'blocked' => $viewer]);
        $hidden = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $blocked]);
        ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $blocker]);
        // A reply under a comment the viewer cannot see would answer nothing on their screen.
        ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $this->member('lea'), 'parent' => $hidden]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    public function test_a_private_wall_has_private_comments(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien', isPublic: false)]);

        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments');

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

    /** Its owner still reads the comments on a private wall (#1317). */
    public function test_the_owner_reads_the_comments_on_their_private_wall(): void
    {
        $owner = $this->member('julien', isPublic: false);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-posts/' . $post->id . '/comments',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    public function test_a_wall_hidden_by_a_block_has_no_comments_to_read(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        UserBlockFactory::new()->create(['blocker' => $owner, 'blocked' => $viewer]);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile-posts/' . $post->id . '/comments');

        $this->assertPostNotFound();
    }

    public function test_an_unknown_post_has_no_comments(): void
    {
        $this->client->request('GET', '/api/user/profile-posts/0195f3a0-0000-7000-8000-000000000000/comments');

        $this->assertPostNotFound();
    }

    public function test_comment_a_post(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $chloe = $this->member('chloe');

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => '  Superbe clip !  ',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $comment = $this->onlyCommentOf($chloe);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-post-comments/' . $comment->id,
            '@type' => 'ProfilePostComment',
            'id' => $comment->id,
            'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'chloe', 'display_name' => 'chloe'],
            'content' => 'Superbe clip !',
            'creation_datetime' => $comment->creationDatetime->format(\DateTimeInterface::ATOM),
            'can_delete' => true,
        ]);
        $this->assertSame($post->id, $comment->post->id);
        $this->assertNull($comment->parent);
    }

    public function test_reply_to_a_comment(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $parent = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $this->member('chloe')]);

        $this->client->loginUser($owner);
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => 'Merci !',
            'parent_id' => $parent->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $reply = $this->onlyCommentOf($owner);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePostComment',
            '@id' => '/api/user/profile-post-comments/' . $reply->id,
            '@type' => 'ProfilePostComment',
            'id' => $reply->id,
            'parent_id' => $parent->id,
            'author' => ['@type' => 'ProfilePostCommentAuthor', 'username' => 'julien', 'display_name' => 'julien'],
            'content' => 'Merci !',
            'creation_datetime' => $reply->creationDatetime->format(\DateTimeInterface::ATOM),
            'can_delete' => true,
        ]);
    }

    public function test_a_reply_cannot_be_answered(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $root = ProfilePostCommentFactory::new()->create(['post' => $post]);
        $reply = ProfilePostCommentFactory::new()->create(['post' => $post, 'parent' => $root]);

        $this->client->loginUser($this->member('chloe'));
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => 'Trop profond',
            'parent_id' => $reply->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 422,
            'type' => '/errors/422',
            'detail' => 'On ne peut pas répondre à une réponse',
            'description' => 'On ne peut pas répondre à une réponse',
        ]);
    }

    public function test_a_parent_from_another_post_is_not_found(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $elsewhere = ProfilePostCommentFactory::new()->create();

        $this->client->loginUser($this->member('chloe'));
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => 'Ailleurs',
            'parent_id' => $elsewhere->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertCommentNotFound();
    }

    public function test_a_comment_by_someone_blocked_either_way_cannot_be_answered(): void
    {
        $viewer = $this->member('chloe');
        $marc = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $marc, 'blocked' => $viewer]);
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $parent = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $marc]);

        $this->client->loginUser($viewer);
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => 'Coucou',
            'parent_id' => $parent->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertCommentNotFound();
    }

    public function test_a_blank_comment_is_refused(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->loginUser($this->member('chloe'));
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => '   ',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Écris un commentaire',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'content: Écris un commentaire',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'content: Écris un commentaire',
        ]);
    }

    public function test_a_comment_is_at_most_1000_characters(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->loginUser($this->member('chloe'));
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => str_repeat('a', 1001),
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Un commentaire fait au plus 1000 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => 'content: Un commentaire fait au plus 1000 caractères',
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'description' => 'content: Un commentaire fait au plus 1000 caractères',
        ]);
    }

    public function test_a_wall_hidden_by_a_block_takes_no_comment(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $owner]);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);

        $this->client->loginUser($viewer);
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => 'Coucou',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertPostNotFound();
    }

    public function test_a_visitor_cannot_comment(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);

        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => 'Coucou',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_the_rate_limit(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $chloe = $this->member('chloe');
        self::getContainer()->get('limiter.profile_post_comment')->create($chloe->getUserIdentifier())->consume(5);

        $this->client->loginUser($chloe);
        $this->client->jsonRequest('POST', '/api/user/profile-posts/' . $post->id . '/comments', [
            'content' => 'Encore un',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/429',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Rate Limit Exceeded',
            'status' => 429,
            'type' => '/errors/429',
            'description' => 'Rate Limit Exceeded',
        ]);
    }

    public function test_its_author_deletes_a_comment_and_its_replies_go_with_it(): void
    {
        $chloe = $this->member('chloe');
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $comment = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $chloe]);
        $reply = ProfilePostCommentFactory::new()->create(['post' => $post, 'parent' => $comment]);
        $other = ProfilePostCommentFactory::new()->create(['post' => $post]);
        $ids = [$comment->id, $reply->id, $other->id];

        $this->client->loginUser($chloe);
        $this->client->request('DELETE', '/api/user/profile-post-comments/' . $comment->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([$ids[2]], $this->remainingCommentIds($ids));
    }

    public function test_the_wall_owner_deletes_someone_else_s_comment(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner]);
        $comment = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $this->member('chloe')]);
        $id = $comment->id;

        $this->client->loginUser($owner);
        $this->client->request('DELETE', '/api/user/profile-post-comments/' . $id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([], $this->remainingCommentIds([$id]));
    }

    public function test_someone_else_cannot_delete_a_comment(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $comment = ProfilePostCommentFactory::new()->create(['post' => $post, 'author' => $this->member('chloe')]);
        $id = $comment->id;

        $this->client->loginUser($this->member('marc'));
        $this->client->request('DELETE', '/api/user/profile-post-comments/' . $id);

        $this->assertCommentNotFound();
        $this->assertSame([$id], $this->remainingCommentIds([$id]));
    }

    private function assertPostNotFound(): void
    {
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

    private function member(string $username, bool $isPublic = true): User
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
        $user->profile->isPublic = $isPublic;
        \Zenstruck\Foundry\Persistence\save($user);

        return $user;
    }

    private function onlyCommentOf(User $author): ProfilePostComment
    {
        $comments = self::getContainer()->get(ProfilePostCommentRepository::class)->findBy(['author' => $author->id]);
        $this->assertCount(1, $comments);

        return $comments[0];
    }

    /**
     * @param list<string|null> $ids
     *
     * @return list<string>
     */
    private function remainingCommentIds(array $ids): array
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $remaining = [];
        foreach ($ids as $id) {
            if ($entityManager->find(ProfilePostComment::class, $id) !== null) {
                $remaining[] = (string) $id;
            }
        }

        return $remaining;
    }
}
