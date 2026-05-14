<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class ForumImageUploadTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_upload_unauthenticated_returns_401(): void
    {
        $file = new UploadedFile(__DIR__ . '/fixtures/image-ok.jpeg', 'image-ok.jpeg');

        $this->client->request('POST', '/api/forum/upload-image', [], ['imageFile' => $file], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_upload_image_succeeds(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $file = new UploadedFile(__DIR__ . '/fixtures/image-ok.jpeg', 'image-ok.jpeg');
        $this->client->loginUser($user);
        $this->client->request('POST', '/api/forum/upload-image', [], ['imageFile' => $file], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = $this->getResponseAsArray();
        $this->assertNotEmpty($response['uri']);
        $this->assertJsonEquals([
            '@context' => $response['@context'],
            '@id' => $response['@id'],
            '@type' => 'ForumImageUploadOutput',
            'uri' => $response['uri'],
        ]);
    }

    public function test_upload_with_no_image_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/forum/upload-image', [], [], [
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
