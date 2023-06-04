<?php

namespace App\Tests\Api\Gallery;

use App\Entity\Gallery;
use App\Entity\Image\GalleryImage;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\GalleryImageFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GalleryGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_publications(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $sub2 = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $gallery1 = GalleryFactory::new([
            'author'              => $author,
            'description'         => 'Description gallery 1',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'slug'                => 'gallery-slug-1',
            'status'              => Gallery::STATUS_ONLINE,
            'title'               => 'Title gallery 1',
        ])->create()->object();
        $image = GalleryImageFactory::new(['gallery' => $gallery1])->create();
        $gallery2 = GalleryFactory::new([
            'author'              => $author,
            'description'         => 'Description gallery 2',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'slug'                => 'gallery-slug-2',
            'status'              => Gallery::STATUS_ONLINE,
            'title'               => 'Title gallery 2',
            'viewCache'           => ViewCacheFactory::new(['count' => 20])->create(),
        ])->create()->object();

        // not taken (status) :
        GalleryFactory::new(['author' => $author, 'status' => Gallery::STATUS_PENDING,])->create()->object();
        GalleryFactory::new(['author' => $author, 'status' => Gallery::STATUS_DRAFT,])->create()->object();

        $this->client->request('GET', '/api/galleries', [
            'order' => ['publication_datetime' => 'asc'],
        ]);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/Gallery',
            '@id'              => '/api/galleries',
            '@type'            => 'hydra:Collection',
            'hydra:member'     => [
                [
                    'title'                => 'Title gallery 2',
                    'publication_datetime' => '2000-01-02T02:03:04+00:00',
                    'author'               => ['username' => 'user_admin'],
                    'cover_image'          => null,
                    'slug'                 => 'gallery-slug-2',
                    'view_cache'           => [
                        'count' => 20,
                    ],
                    'image_count'          => 0,
                ],
                [
                    'title'                => 'Title gallery 1',
                    'publication_datetime' => '2020-01-02T02:03:04+00:00',
                    'author'               => ['username' => 'user_admin'],
                    'cover_image'          => null,
                    'slug'                 => 'gallery-slug-1',
                    'view_cache'           => null,
                    'image_count'          => 1,
                ],
            ],
            'hydra:totalItems' => 2,
            'hydra:view'       => [
                '@id'   => '/api/galleries?order%5Bpublication_datetime%5D=asc',
                '@type' => 'hydra:PartialCollectionView',
            ],
            'hydra:search'     => [
                '@type'                        => 'hydra:IriTemplate',
                'hydra:template'               => '/api/galleries{?order[publication_datetime]}',
                'hydra:variableRepresentation' => 'BasicRepresentation',
                'hydra:mapping'                => [
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'order[publication_datetime]',
                        'property' => 'publication_datetime',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }
}