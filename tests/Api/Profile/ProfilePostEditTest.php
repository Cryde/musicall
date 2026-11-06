<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\Profile\ProfilePostImage;
use App\Entity\User;
use App\Repository\Profile\ProfilePostRepository;
use App\Service\Procedure\User\DeleteAccountProcedure;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\User\UserFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** Editing, pinning and deleting your own wall post (#1316). */
#[ResetDatabase]
class ProfilePostEditTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_new_text_marks_the_post_as_edited(): void
    {
        $author = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $author, 'content' => 'Avant']);

        $this->client->loginUser($author);
        $this->client->jsonRequest('PATCH', '/api/user/profile-posts/' . $post->id, [
            'content' => ' Après ',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $stored = $this->reload($post);
        $this->assertNotNull($stored->updateDatetime);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePost',
            '@id' => '/api/user/profile-posts/' . $post->id,
            '@type' => 'ProfilePost',
            'id' => $post->id,
            'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
            'content' => 'Après',
            'photos' => [],
            'is_pinned' => false,
            'creation_datetime' => '2026-11-01T10:00:00+00:00',
            'update_datetime' => $stored->updateDatetime->format(\DateTimeInterface::ATOM),
            'can_edit' => true,
        ]);
    }

    public function test_pinning_unpins_the_previous_post_and_leaves_the_text_alone(): void
    {
        $author = $this->member('julien');
        $previous = ProfilePostFactory::new()->create(['author' => $author, 'isPinned' => true]);
        $post = ProfilePostFactory::new()->create(['author' => $author, 'content' => 'À épingler']);

        $this->client->loginUser($author);
        $this->client->jsonRequest('PATCH', '/api/user/profile-posts/' . $post->id, [
            'is_pinned' => true,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePost',
            '@id' => '/api/user/profile-posts/' . $post->id,
            '@type' => 'ProfilePost',
            'id' => $post->id,
            'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
            'content' => 'À épingler',
            'photos' => [],
            'is_pinned' => true,
            'creation_datetime' => '2026-11-01T10:00:00+00:00',
            'can_edit' => true,
        ]);
        $this->assertFalse($this->reload($previous)->isPinned);
        // Leaving the text out is not an edit: no « modifié ».
        $this->assertNull($this->reload($post)->updateDatetime);
    }

    public function test_emptying_a_text_only_post_is_refused(): void
    {
        $author = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $author, 'content' => 'Seul texte']);

        $this->client->loginUser($author);
        $this->client->jsonRequest('PATCH', '/api/user/profile-posts/' . $post->id, [
            'content' => '',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Écris un texte, ajoute des photos ou un lien',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'content: Écris un texte, ajoute des photos ou un lien',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'content: Écris un texte, ajoute des photos ou un lien',
        ]);
    }

    public function test_someone_else_s_post_does_not_exist_for_you(): void
    {
        $post = ProfilePostFactory::new()->create(['author' => $this->member('julien')]);
        $other = $this->member('chloe');

        $this->client->loginUser($other);
        $this->client->jsonRequest('PATCH', '/api/user/profile-posts/' . $post->id, [
            'content' => 'Pirate',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

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

    public function test_deleting_a_post_removes_its_photos(): void
    {
        $author = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $author]);
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $filesystem->write('/profile_post_images/' . $author->id . '/photo.webp', 'webp-bytes');
        $image = new ProfilePostImage();
        $image->post = $post;
        $image->mimeType = 'image/webp';
        $image->size = 10;
        $image->originalName = 'photo.webp';
        $image->storagePath = 'photo.webp';
        $post->addImage($image);
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($image);
        $entityManager->flush();
        // The request shares this entity manager: it must load the post as the database holds it.
        $entityManager->clear();
        $postId = $post->id;

        $this->client->disableReboot();
        $this->client->loginUser($author);
        $this->client->request('DELETE', '/api/user/profile-posts/' . $postId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertNull(self::getContainer()->get(ProfilePostRepository::class)->find($postId));
        $this->assertFalse($filesystem->fileExists('/profile_post_images/' . $author->id . '/photo.webp'));
    }

    public function test_deleting_the_account_removes_the_wall_and_its_photos(): void
    {
        $author = $this->member('julien');
        $post = ProfilePostFactory::new()->create(['author' => $author]);
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $filesystem->write('/profile_post_images/' . $author->id . '/photo.webp', 'webp-bytes');
        $image = new ProfilePostImage();
        $image->mimeType = 'image/webp';
        $image->size = 10;
        $image->originalName = 'photo.webp';
        $image->storagePath = 'photo.webp';
        $post->addImage($image);
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($image);
        $entityManager->flush();
        $entityManager->clear();
        $postId = $post->id;

        $user = $entityManager->find(User::class, $author->id);
        $this->assertInstanceOf(User::class, $user);
        self::getContainer()->get(DeleteAccountProcedure::class)->process($user);

        $this->assertNull(self::getContainer()->get(ProfilePostRepository::class)->find($postId));
        $this->assertFalse($filesystem->fileExists('/profile_post_images/' . $author->id . '/photo.webp'));
    }

    private function member(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }

    private function reload(ProfilePost $post): ProfilePost
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $stored = $entityManager->find(ProfilePost::class, $post->id);
        $this->assertInstanceOf(ProfilePost::class, $stored);

        return $stored;
    }
}
