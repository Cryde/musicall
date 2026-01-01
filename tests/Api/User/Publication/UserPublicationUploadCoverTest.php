<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserPublicationUploadCoverTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_upload_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $file = new UploadedFile(__DIR__ . '/fixtures/image-ok.jpeg', 'image-ok.jpeg');
        $this->client->request('POST', '/api/user/publications/' . $publication->getId() . '/upload-cover', [], ['imageFile' => $file], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_upload_cover(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $file = new UploadedFile(__DIR__ . '/fixtures/image-ok.jpeg', 'image-ok.jpeg');
        $this->client->loginUser($user->_real());
        $this->client->request('POST', '/api/user/publications/' . $publication->getId() . '/upload-cover', [], ['imageFile' => $file], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertJsonContains([
            '@type' => 'UserPublicationUploadCoverOutput',
        ]);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('uri', $response);
        $this->assertNotEmpty($response['uri']);
    }

    public function test_upload_cover_not_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $otherUser = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $owner,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $file = new UploadedFile(__DIR__ . '/fixtures/image-ok.jpeg', 'image-ok.jpeg');
        $this->client->loginUser($otherUser->_real());
        $this->client->request('POST', '/api/user/publications/' . $publication->getId() . '/upload-cover', [], ['imageFile' => $file], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_upload_cover_not_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_ONLINE,
        ]);

        $file = new UploadedFile(__DIR__ . '/fixtures/image-ok.jpeg', 'image-ok.jpeg');
        $this->client->loginUser($user->_real());
        $this->client->request('POST', '/api/user/publications/' . $publication->getId() . '/upload-cover', [], ['imageFile' => $file], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_upload_cover_publication_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $file = new UploadedFile(__DIR__ . '/fixtures/image-ok.jpeg', 'image-ok.jpeg');
        $this->client->loginUser($user->_real());
        $this->client->request('POST', '/api/user/publications/999999/upload-cover', [], ['imageFile' => $file], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_upload_cover_too_big(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $file = new UploadedFile(__DIR__ . '/fixtures/image-too-big.jpg', 'image-too-big.jpg');
        $this->client->loginUser($user->_real());
        $this->client->request('POST', '/api/user/publications/' . $publication->getId() . '/upload-cover', [], ['imageFile' => $file], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/7f87163d-878f-47f5-99ba-a8eb723a1ab2',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'image_file: La largeur de l\'image est trop grande (4100px). La largeur maximale autorisée est de 4000px.',
            'status' => 422,
            'type' => '/validation_errors/7f87163d-878f-47f5-99ba-a8eb723a1ab2',
            'description' => 'image_file: La largeur de l\'image est trop grande (4100px). La largeur maximale autorisée est de 4000px.',
            'violations' => [
                [
                    'propertyPath' => 'image_file',
                    'message' => 'La largeur de l\'image est trop grande (4100px). La largeur maximale autorisée est de 4000px.',
                    'code' => '7f87163d-878f-47f5-99ba-a8eb723a1ab2',
                ],
            ],
        ]);
    }

    public function test_upload_with_no_cover(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('POST', '/api/user/publications/' . $publication->getId() . '/upload-cover', [], [], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'image_file: Cette valeur ne doit pas être nulle.',
            'status' => 422,
            'type' => '/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            'description' => 'image_file: Cette valeur ne doit pas être nulle.',
            'violations' => [
                [
                    'propertyPath' => 'image_file',
                    'message' => 'Cette valeur ne doit pas être nulle.',
                    'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720',
                ],
            ],
        ]);
    }
}
