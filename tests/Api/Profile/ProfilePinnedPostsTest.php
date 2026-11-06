<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Profile\ProfilePostCommentFactory;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\Profile\ProfilePostLikeFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The post a member keeps above their wall (#1316), read like the wall. */
#[ResetDatabase]
class ProfilePinnedPostsTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_pinned_post_counts_what_the_viewer_can_read(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        $blocked = $this->member('marc');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blocked]);
        ProfilePostFactory::new()->create(['author' => $owner, 'content' => 'Pas épinglé']);
        $pinned = ProfilePostFactory::new()->create([
            'author' => $owner,
            'content' => 'Épinglé',
            'isPinned' => true,
            'creationDatetime' => new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        ]);
        ProfilePostLikeFactory::new()->create(['post' => $pinned, 'user' => $viewer]);
        ProfilePostLikeFactory::new()->create(['post' => $pinned, 'user' => $blocked]);
        ProfilePostCommentFactory::new()->create(['post' => $pinned, 'author' => $owner]);
        ProfilePostCommentFactory::new()->create(['post' => $pinned, 'author' => $blocked]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile/julien/pinned-posts');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePost',
            '@id' => '/api/user/profile/julien/pinned-posts',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/user/profile-posts/' . $pinned->id,
                    '@type' => 'ProfilePost',
                    'id' => $pinned->id,
                    'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => 'Épinglé',
                    'photos' => [],
                    'is_pinned' => true,
                    'creation_datetime' => '2026-01-01T10:00:00+00:00',
                    'can_edit' => false,
                    // Without anyone the viewer blocked.
                    'like_count' => 1,
                    'has_liked' => true,
                    'comment_count' => 1,
                ],
            ],
        ]);
    }

    public function test_nothing_pinned_is_an_empty_list(): void
    {
        $owner = $this->member('julien');
        ProfilePostFactory::new()->create(['author' => $owner]);

        $this->client->request('GET', '/api/user/profile/julien/pinned-posts');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePost',
            '@id' => '/api/user/profile/julien/pinned-posts',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    public function test_a_member_the_owner_blocked_sees_no_pinned_post(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        ProfilePostFactory::new()->create(['author' => $owner, 'isPinned' => true]);
        UserBlockFactory::new()->create(['blocker' => $owner, 'blocked' => $viewer]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile/julien/pinned-posts');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePost',
            '@id' => '/api/user/profile/julien/pinned-posts',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    public function test_a_member_who_blocked_the_owner_sees_no_pinned_post(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        ProfilePostFactory::new()->create(['author' => $owner, 'isPinned' => true]);
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $owner]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile/julien/pinned-posts');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePost',
            '@id' => '/api/user/profile/julien/pinned-posts',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    public function test_a_private_profile_keeps_its_pinned_post(): void
    {
        $owner = $this->member('julien', isPublic: false);
        ProfilePostFactory::new()->create(['author' => $owner, 'isPinned' => true]);

        $this->client->request('GET', '/api/user/profile/julien/pinned-posts');

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

    public function test_an_unknown_member_has_no_pinned_post(): void
    {
        $this->client->request('GET', '/api/user/profile/nobody/pinned-posts');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Profil non trouvé',
            'description' => 'Profil non trouvé',
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
