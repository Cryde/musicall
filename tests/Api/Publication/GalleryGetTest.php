<?php

namespace Api\Publication;

use App\Entity\Gallery;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\GalleryImageFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GalleryGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_item_gallery(): void
    {
        $author = UserFactory::new()->asAdminUser()->create();
        $viewCache = ViewCacheFactory::new(['count' => 123])->create();
        $cover = GalleryImageFactory::new(['image_name' => 'test.jpg'])->create();

        GalleryFactory::new([
            'author'              => $author,
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'updateDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'description'    => 'Petite description de la gallery',
            'slug'                => 'titre-de-la-gallery',
            'status'              => Gallery::STATUS_ONLINE,
            'title'               => 'Titre de la gallery',
            'viewCache'           => $viewCache,
            'coverImage'          => $cover->object(),
        ])->create();
        $this->client->request('GET', '/api/galleries/titre-de-la-gallery');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Gallery',
            '@id' => '/api/galleries/titre-de-la-gallery',
            '@type' => 'Gallery',
            'title'                => 'Titre de la gallery',
            'author'               => [
                '@type'    => 'Author',
                'username' => 'user_admin',
            ],
            'slug'                 => 'titre-de-la-gallery',
            'publication_datetime' => '2022-01-02T02:03:04+00:00',
            'category'             => [
                '@type'    => 'Category',
                'id'       => 0,
                'title'    => 'gallery',
                'slug'     => 'gallery',
            ],
            'description'          => 'Petite description de la gallery',
            'cover'                => [
                '@type'     => 'Cover',
                'cover_url' => 'http://musicall.test/media/cache/resolve/gallery_image_filter_medium/images/gallery/1/test.jpg',
            ],
        ]);
    }

    public function test_get_item_publication_not_found(): void
    {
        $this->client->request('GET', '/api/galleries/not_found');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@id' => '/api/errors/404',
            '@type' => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Gallery inexistante',
            'title'             => 'An error occurred',
            'detail'            => 'Gallery inexistante',
            'status'            => 404,
            'type'              => '/errors/404',
        ]);
    }
}