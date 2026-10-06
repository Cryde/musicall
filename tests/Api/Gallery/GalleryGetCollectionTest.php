<?php

declare(strict_types=1);

namespace App\Tests\Api\Gallery;

use App\Entity\Gallery;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\GalleryImageFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class GalleryGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_galleries(): void
    {
        PublicationSubCategoryFactory::new()->asChronique()->create();
        PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $gallery1 = GalleryFactory::new([
            'author'              => $author,
            'description'         => 'Description gallery 1',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'slug'                => 'gallery-slug-1',
            'status'              => Gallery::STATUS_ONLINE,
            'title'               => 'Title gallery 1',
        ])->create();
        $cover1 = GalleryImageFactory::new(['imageName' => 'cover.jpg', 'gallery' => $gallery1])->create();
        $gallery1->coverImage = $cover1;
        \Zenstruck\Foundry\Persistence\save($gallery1);
        $gallery2 = GalleryFactory::new([
            'author'              => $author,
            'description'         => 'Description gallery 2',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'slug'                => 'gallery-slug-2',
            'status'              => Gallery::STATUS_ONLINE,
            'title'               => 'Title gallery 2',
        ])->create();

        // not taken (status) :
        GalleryFactory::new(['author' => $author, 'status' => Gallery::STATUS_PENDING,])->create();
        GalleryFactory::new(['author' => $author, 'status' => Gallery::STATUS_DRAFT,])->create();

        $this->client->request('GET', '/api/galleries', [
            'order' => ['publication_datetime' => 'asc'],
        ]);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/Gallery',
            '@id'              => '/api/galleries',
            '@type'            => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/galleries/' . $gallery2->id,
                    '@type'                => 'Gallery',
                    'id'                   => $gallery2->id,
                    'title'                => 'Title gallery 2',
                    'publication_datetime' => '2000-01-02T02:03:04+00:00',
                    'author'               => [
                        '@id'      => '/api/users/' . $author->id,
                        '@type'    => 'User',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'cover_image'          => null,
                    'slug'                 => 'gallery-slug-2',
                    'image_count'          => 0,
                ],
                [
                    '@id'                  => '/api/galleries/' . $gallery1->id,
                    '@type'                => 'Gallery',
                    'id'                   => $gallery1->id,
                    'title'                => 'Title gallery 1',
                    'publication_datetime' => '2020-01-02T02:03:04+00:00',
                    'author'               => [
                        '@id'      => '/api/users/' . $author->id,
                        '@type'    => 'User',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'cover_image'          => 'http://musicall.test/media/cache/resolve/gallery_image_filter_medium/images/gallery/' . $gallery1->id . '/cover.jpg',
                    'slug'                 => 'gallery-slug-1',
                    'image_count'          => 1,
                ],
            ],
            'totalItems' => 2,
            'view'       => [
                '@id'   => '/api/galleries?order%5Bpublication_datetime%5D=asc',
                '@type' => 'PartialCollectionView',
            ],
            'search'     => [
                '@type'                        => 'IriTemplate',
                'template'               => '/api/galleries{?order[publication_datetime]}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping'                => [
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

    /** One author per naming case, each named without a query of their own (#1118). */
    public function test_authors_are_named_by_their_public_profile_name_next_to_their_username(): void
    {
        $named = UserFactory::new()->create(['username' => 'androidtest_123', 'email' => 'androidtest@test.com']);
        $named->profile->displayName = 'Alexandre Martin';
        $private = UserFactory::new()->create(['username' => 'crydetest', 'email' => 'crydetest@test.com']);
        $private->profile->displayName = 'Samuel Dupont';
        $private->profile->isPublic = false;
        $departed = UserFactory::new()->create([
            'username' => 'deleted_c7c9f2e1',
            'email' => 'deleted_c7c9f2e1@email.com',
            'deletionDatetime' => new \DateTimeImmutable('2024-06-01 09:00:00'),
        ]);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $galleries = [];
        foreach ([$named, $private, $departed] as $index => $author) {
            $galleries[] = GalleryFactory::new([
                'author' => $author,
                'publicationDatetime' => new \DateTime(sprintf('2022-01-0%d 10:00:00', $index + 1)),
                'slug' => 'gallery-' . $index,
                'status' => Gallery::STATUS_ONLINE,
                'title' => 'Gallery ' . $index,
            ])->create();
        }

        $this->client->enableProfiler();
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/galleries', ['order' => ['publication_datetime' => 'asc']]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Gallery',
            '@id' => '/api/galleries',
            '@type' => 'Collection',
            'member' => [
                $this->expectedGallery($galleries[0], $named, 'Alexandre Martin', null),
                // A private profile keeps its name to itself.
                $this->expectedGallery($galleries[1], $private, 'crydetest', null),
                $this->expectedGallery($galleries[2], $departed, 'Utilisateur supprimé', '2024-06-01T09:00:00+00:00'),
            ],
            'totalItems' => 3,
            'view' => [
                '@id' => '/api/galleries?order%5Bpublication_datetime%5D=asc',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/galleries{?order[publication_datetime]}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'order[publication_datetime]',
                        'property' => 'publication_datetime',
                        'required' => false,
                    ],
                ],
            ],
        ]);
        $this->assertNoQueryReadsTable('user_profile', 'An author profile must come with the list, never in a query of its own');
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedGallery(Gallery $gallery, object $author, string $displayName, ?string $deletionDatetime): array
    {
        return [
            '@id' => '/api/galleries/' . $gallery->id,
            '@type' => 'Gallery',
            'id' => $gallery->id,
            'title' => $gallery->title,
            'publication_datetime' => \DateTimeImmutable::createFromInterface($gallery->publicationDatetime)->format(\DateTimeInterface::ATOM),
            'author' => [
                '@id' => '/api/users/' . $author->id,
                '@type' => 'User',
                'username' => $author->username,
                'display_name' => $displayName,
                'deletion_datetime' => $deletionDatetime,
            ],
            'cover_image' => null,
            'slug' => $gallery->slug,
            'image_count' => 0,
        ];
    }
}
