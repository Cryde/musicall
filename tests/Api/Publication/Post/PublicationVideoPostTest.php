<?php

namespace Api\Publication\Post;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

// note:  This test will only cover constraints for now
class PublicationVideoPostTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function testEmptyValues()
    {
        $user1 = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user1->object());
        $this->client->jsonRequest('POST', '/api/publications/video/add',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertJsonEquals([
            'status'            => 422,
            'violations'        => [
                [
                    'propertyPath' => 'url',
                    'message'      => 'Cette valeur ne doit pas être vide.',
                    'code'         => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'title',
                    'message'      => 'Cette valeur ne doit pas être vide.',
                    'code'         => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'description',
                    'message'      => 'Cette valeur ne doit pas être vide.',
                    'code'         => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail'            => 'url: Cette valeur ne doit pas être vide.
title: Cette valeur ne doit pas être vide.
description: Cette valeur ne doit pas être vide.',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'url: Cette valeur ne doit pas être vide.
title: Cette valeur ne doit pas être vide.
description: Cette valeur ne doit pas être vide.',
            'type'              => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3;2=c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title'             => 'An error occurred',
        ]);
    }

    public function testWrongUrl()
    {
        $user1 = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user1->object());
        $this->client->jsonRequest('POST', '/api/publications/video/add',
            [
                'url' => 'wrong_url',
                'title' => 'title',
                'description' => 'this is a description',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertJsonEquals([
            'status'            => 422,
            'violations'        => [
                [
                    'propertyPath' => 'url',
                    'message' => 'Cette valeur n\'est pas une URL valide.',
            'code' => '57c2f299-1154-4870-89bb-ef3b1f5ad229',
                ],
            ],
            'detail'            => 'url: Cette valeur n\'est pas une URL valide.',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'url: Cette valeur n\'est pas une URL valide.',
            'type'              => '/validation_errors/57c2f299-1154-4870-89bb-ef3b1f5ad229',
            'title'             => 'An error occurred',
        ]);
    }

    public function testNotYoutubeUrl()
    {
        $user1 = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user1->object());
        $this->client->jsonRequest('POST', '/api/publications/video/add',
            [
                'url' => 'https://musicall.com',
                'title' => 'title',
                'description' => 'this is a description',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertJsonEquals([
            'status'            => 422,
            'violations'        => [
                [
                    'propertyPath' => 'url',
                    'message' => 'L\'url de cette vidéo n\'est pas supportée',
                    'code' => 'music_all_f03dc5f4-8ba0-11ee-b9d1-0242ac120002',
                ],
            ],
            'detail'            => 'url: L\'url de cette vidéo n\'est pas supportée',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'url: L\'url de cette vidéo n\'est pas supportée',
            'type'              => '/validation_errors/music_all_f03dc5f4-8ba0-11ee-b9d1-0242ac120002',
            'title'             => 'An error occurred',
        ]);
    }

    public function testExistingVideo()
    {
        $user1 = UserFactory::new()->asBaseUser()->create();
        $sub = PublicationSubCategoryFactory::new()->asDecouvertes()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        PublicationFactory::new([
            'author'              => $author,
            'content'             => 'kcelgrGY1h8',
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'editionDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Petite description de la publication 1',
            'slug'                => 'titre-de-la-publication-1',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Titre de la publication 1',
            'type'                => Publication::TYPE_VIDEO,
            'viewCache'           => ViewCacheFactory::new(['count' => 10])->create(),
        ])->create()->object();

        $this->client->loginUser($user1->object());
        $this->client->jsonRequest('POST', '/api/publications/video/add',
            [
                'url' => 'https://www.youtube.com/watch?v=kcelgrGY1h8',
                'title' => 'title',
                'description' => 'this is a description',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertJsonEquals([
            'status'            => 422,
            'violations'        => [
                [
                    'propertyPath' => 'url',
                    'message' => 'Cette vidéo existe déjà sur MusicAll',
                    'code' => 'music_all_99153e73-dd44-4557-90aa-3c0e354fce62',
                ],
            ],
            'detail'            => 'url: Cette vidéo existe déjà sur MusicAll',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'url: Cette vidéo existe déjà sur MusicAll',
            'type'              => '/validation_errors/music_all_99153e73-dd44-4557-90aa-3c0e354fce62',
            'title'             => 'An error occurred',
        ]);
    }

    public function test_post_without_logged_in()
    {
        $this->client->jsonRequest('POST', '/api/publications/video/add', [], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }
}