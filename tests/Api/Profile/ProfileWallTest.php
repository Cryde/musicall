<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostImage;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** Reading a member's wall and its photos (#1316). */
#[ResetDatabase]
class ProfileWallTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_pinned_post_comes_first_then_the_newest(): void
    {
        $owner = $this->member('julien');
        $pinned = ProfilePostFactory::new()->create([
            'author' => $owner,
            'content' => 'Épinglé depuis longtemps',
            'isPinned' => true,
            'creationDatetime' => new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        ]);
        $posts = [];
        foreach (range(1, 11) as $day) {
            $posts[$day] = ProfilePostFactory::new()->create([
                'author' => $owner,
                'content' => 'Post ' . $day,
                'creationDatetime' => new \DateTimeImmutable(sprintf('2026-10-%02dT10:00:00+00:00', $day)),
            ]);
        }

        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWall',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'ProfileWall',
            'username' => 'julien',
            'pinned_post' => [
                '@type' => 'ProfilePost',
                'id' => $pinned->id,
                'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                'content' => 'Épinglé depuis longtemps',
                'photos' => [],
                'is_pinned' => true,
                'creation_datetime' => '2026-01-01T10:00:00+00:00',
                'can_edit' => false,
            ],
            // Newest first, ten to a page: days 11 down to 2, and day 1 waits for the next page.
            'posts' => array_map(static fn (int $day): array => [
                '@type' => 'ProfilePost',
                'id' => $posts[$day]->id,
                'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                'content' => 'Post ' . $day,
                'photos' => [],
                'is_pinned' => false,
                'creation_datetime' => sprintf('2026-10-%02dT10:00:00+00:00', $day),
                'can_edit' => false,
            ], range(11, 2)),
            'has_more' => true,
        ]);
    }

    public function test_the_next_page_starts_after_the_last_post_held(): void
    {
        $owner = $this->member('julien');
        ProfilePostFactory::new()->create(['author' => $owner, 'isPinned' => true]);
        $newer = ProfilePostFactory::new()->create([
            'author' => $owner,
            'creationDatetime' => new \DateTimeImmutable('2026-10-02T10:00:00+00:00'),
        ]);
        $older = ProfilePostFactory::new()->create([
            'author' => $owner,
            'content' => 'Le plus ancien',
            'creationDatetime' => new \DateTimeImmutable('2026-10-01T10:00:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/julien/wall?before=' . $newer->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWall',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'ProfileWall',
            'username' => 'julien',
            'posts' => [
                [
                    '@type' => 'ProfilePost',
                    'id' => $older->id,
                    'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => 'Le plus ancien',
                    'photos' => [],
                    'is_pinned' => false,
                    'creation_datetime' => '2026-10-01T10:00:00+00:00',
                    'can_edit' => false,
                ],
            ],
            'has_more' => false,
        ]);
    }

    public function test_posts_written_in_the_same_second_are_paged_by_id(): void
    {
        $owner = $this->member('julien');
        $sameSecond = new \DateTimeImmutable('2026-10-05T10:00:00+00:00');
        $posts = [];
        foreach (['Un', 'Deux', 'Trois'] as $content) {
            $posts[] = ProfilePostFactory::new()->create(['author' => $owner, 'content' => $content, 'creationDatetime' => $sameSecond]);
        }
        // The server orders a shared second by id, newest first.
        usort($posts, static fn (ProfilePost $a, ProfilePost $b): int => strcmp((string) $b->id, (string) $a->id));
        [, $middle, $oldest] = $posts;

        $this->client->request('GET', '/api/user/profile/julien/wall?before=' . $middle->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWall',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'ProfileWall',
            'username' => 'julien',
            'posts' => [
                [
                    '@type' => 'ProfilePost',
                    'id' => $oldest->id,
                    'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => $oldest->content,
                    'photos' => [],
                    'is_pinned' => false,
                    'creation_datetime' => '2026-10-05T10:00:00+00:00',
                    'can_edit' => false,
                ],
            ],
            'has_more' => false,
        ]);
    }

    public function test_a_post_lists_its_photos_in_order(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $owner, 'content' => 'Forest National']);
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        foreach (['first.webp', 'second.webp'] as $name) {
            $image = new ProfilePostImage();
            $image->mimeType = 'image/webp';
            $image->size = 10;
            $image->originalName = $name;
            $image->storagePath = $name;
            $post->addImage($image);
            $entityManager->persist($image);
        }
        $entityManager->flush();
        [$first, $second] = $post->images->toArray();
        $entityManager->clear();

        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWall',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'ProfileWall',
            'username' => 'julien',
            'posts' => [
                [
                    '@type' => 'ProfilePost',
                    'id' => $post->id,
                    'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => 'Forest National',
                    'photos' => [
                        ['@type' => 'ProfilePostPhoto', 'id' => $first->id, 'url' => '/api/user/profile/julien/posts/' . $post->id . '/photos/' . $first->id],
                        ['@type' => 'ProfilePostPhoto', 'id' => $second->id, 'url' => '/api/user/profile/julien/posts/' . $post->id . '/photos/' . $second->id],
                    ],
                    'is_pinned' => false,
                    'creation_datetime' => '2026-11-01T10:00:00+00:00',
                    'can_edit' => false,
                ],
            ],
            'has_more' => false,
        ]);
    }

    public function test_the_owner_can_edit_their_posts(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create([
            'author' => $owner,
            'content' => 'Mon post',
            'updateDatetime' => new \DateTimeImmutable('2026-11-02T08:00:00+00:00'),
        ]);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWall',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'ProfileWall',
            'username' => 'julien',
            'posts' => [
                [
                    '@type' => 'ProfilePost',
                    'id' => $post->id,
                    'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                    'content' => 'Mon post',
                    'photos' => [],
                    'is_pinned' => false,
                    'creation_datetime' => '2026-11-01T10:00:00+00:00',
                    'update_datetime' => '2026-11-02T08:00:00+00:00',
                    'can_edit' => true,
                ],
            ],
            'has_more' => false,
        ]);
    }

    public function test_a_member_the_owner_blocked_sees_an_empty_wall(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        ProfilePostFactory::new()->create(['author' => $owner, 'isPinned' => true]);
        ProfilePostFactory::new()->create(['author' => $owner]);
        UserBlockFactory::new()->create(['blocker' => $owner, 'blocked' => $viewer]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWall',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'ProfileWall',
            'username' => 'julien',
            'posts' => [],
            'has_more' => false,
        ]);
    }

    public function test_a_member_who_blocked_the_owner_sees_an_empty_wall(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        ProfilePostFactory::new()->create(['author' => $owner]);
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $owner]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWall',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'ProfileWall',
            'username' => 'julien',
            'posts' => [],
            'has_more' => false,
        ]);
    }

    public function test_a_private_profile_has_a_private_wall(): void
    {
        $owner = $this->member('julien', isPublic: false);
        ProfilePostFactory::new()->create(['author' => $owner]);

        $this->client->request('GET', '/api/user/profile/julien/wall');

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

    public function test_an_unknown_member_has_no_wall(): void
    {
        $this->client->request('GET', '/api/user/profile/nobody/wall');

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

    public function test_a_post_of_another_wall_is_not_a_page_anchor(): void
    {
        $this->member('julien');
        $other = ProfilePostFactory::new()->create(['author' => $this->member('chloe')]);

        $this->client->request('GET', '/api/user/profile/julien/wall?before=' . $other->id);

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

    public function test_a_photo_streams_to_a_visitor(): void
    {
        [$owner, $post, $image] = $this->postWithStoredPhoto('julien');

        $this->client->request('GET', '/api/user/profile/julien/posts/' . $post->id . '/photos/' . $image->id);

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('webp-bytes', $this->client->getInternalResponse()->getContent());
    }

    public function test_a_photo_is_not_served_under_another_post(): void
    {
        [$owner, , $image] = $this->postWithStoredPhoto('julien');
        $otherPost = ProfilePostFactory::new()->create(['author' => $owner]);

        $this->client->request('GET', '/api/user/profile/julien/posts/' . $otherPost->id . '/photos/' . $image->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Photo introuvable',
            'description' => 'Photo introuvable',
        ]);
    }

    public function test_a_photo_is_not_served_to_a_blocked_member(): void
    {
        [$owner, $post, $image] = $this->postWithStoredPhoto('julien');
        $viewer = $this->member('chloe');
        UserBlockFactory::new()->create(['blocker' => $owner, 'blocked' => $viewer]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile/julien/posts/' . $post->id . '/photos/' . $image->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Photo introuvable',
            'description' => 'Photo introuvable',
        ]);
    }

    private function member(string $username, bool $isPublic = true): User
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
        $user->profile->isPublic = $isPublic;
        \Zenstruck\Foundry\Persistence\save($user);

        return $user;
    }

    /**
     * @return array{0: User, 1: ProfilePost, 2: ProfilePostImage}
     */
    private function postWithStoredPhoto(string $username): array
    {
        $owner = $this->member($username);
        $post = ProfilePostFactory::new()->create(['author' => $owner]);

        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $filesystem->write('/profile_post_images/' . $owner->id . '/photo.webp', 'webp-bytes');
        $image = new ProfilePostImage();
        $image->post = $post;
        $image->mimeType = 'image/webp';
        $image->size = 10;
        $image->originalName = 'photo.webp';
        $image->storagePath = 'photo.webp';
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($image);
        $entityManager->flush();

        return [$owner, $post, $image];
    }
}
