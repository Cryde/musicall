<?php

namespace Api\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationCoverFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicationSearchCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public static function setUpBeforeClass(): void
    {
        StaticDriver::setKeepStaticConnections(false);
    }

    public static function tearDownAfterClass(): void
    {
        StaticDriver::setKeepStaticConnections(true);
    }

    public function test_search_publications(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $sub2 = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $thread = CommentThreadFactory::new()->create();

        $pub1 = PublicationFactory::new([
            'author'              => $author,
            'content'             => 'find me 1',
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'editionDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Petite description de la publication 1',
            'slug'                => 'titre-de-la-publication-1',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Titre de la publication 1',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 10])->create(),
            'cover'               => PublicationCoverFactory::new(['image_name' => 'test.jpg'])->create()->object(),
            'thread'              => $thread,
        ])->create();

        $pub2 = PublicationFactory::new([
            'author'              => $author,
            'content'             => 'blabla',
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'editionDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'shortDescription'    => 'find me 2',
            'slug'                => 'titre-de-la-publication-2',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Titre de la publication 2',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 20])->create(),
            'cover'               => PublicationCoverFactory::new(['image_name' => 'test.jpg'])->create()->object(),
            'thread'              => $thread,
        ])->create();

        // not taken (status) :
        $pub3 = PublicationFactory::new([
            'title' => 'find me 3',
            'author' => $author, 'status' => Publication::STATUS_DRAFT, 'subCategory' => $sub,
            'cover'               => PublicationCoverFactory::new(['image_name' => 'test.jpg'])->create()->object(),
            'thread'              => $thread,
        ])->create();
        // not taken (status):
        $pub4 = PublicationFactory::new([
            'title' => 'find me 4',
            'author' => $author, 'status' => Publication::STATUS_PENDING, 'subCategory' => $sub,
            'cover'               => PublicationCoverFactory::new(['image_name' => 'test.jpg'])->create()->object(),
            'thread'              => $thread,
        ])->create();
        // not taken (wrong title):
        $pub5 = PublicationFactory::new([
            'title' => 'hello world',
            'author' => $author, 'status' => Publication::STATUS_ONLINE, 'subCategory' => $sub2,
            'cover'               => PublicationCoverFactory::new(['image_name' => 'test.jpg'])->create()->object(),
            'thread'              => $thread,
        ])->create();

        $objectToDelete = [$sub, $sub2, $author, $thread, $pub1, $pub2, $pub3, $pub4, $pub5];
       // StaticDriver::commit(); //

        $this->client->request('GET', '/api/publications/search', [
            'term' => 'find me'
        ]);

        $subCatId = $sub->object()->getId();
        $threadId = $thread->object()->getId();
        $this->assertResponseIsSuccessful();
        foreach ($objectToDelete as $item) {
            $item->remove();
        }

        $this->assertJsonEquals([
            '@context'         => '/api/contexts/Publication',
            '@id'              => '/api/publications/search',
            '@type'            => 'hydra:Collection',
            'hydra:member'     => [
                [
                    '@id'                  => '/api/publications/titre-de-la-publication-2',
                    '@type'                => 'Publication',
                    'title'                => 'Titre de la publication 2',
                    'author'               => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                    ],
                    'slug'                 => 'titre-de-la-publication-2',
                    'publication_datetime' => '2022-01-02T02:03:04+00:00',
                    'cover'                => [
                        '@type' => 'Cover',
                        'cover_url' => 'http://musicall.test/media/cache/resolve/publication_cover_300x300/images/publication/cover/test.jpg',
                    ],
                    'description'          => 'find me 2',
                    'category' => [
                        '@type' => 'Category',
                        'id' => $subCatId,
                        'title' => 'Chroniques',
                        'slug' => 'chroniques',
                    ],
                    'content' => 'blabla',
                    'thread' => [
                        '@type' => 'Thread',
                        'id' => $threadId,
                    ],
                    'type' => [
                        '@type' => 'Type',
                        'id' => 1,
                        'label' => 'text',
                    ],
                ],
                [
                    '@id'                  => '/api/publications/titre-de-la-publication-1',
                    '@type'                => 'Publication',
                    'title'                => 'Titre de la publication 1',
                    'author'               => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                    ],
                    'slug'                 => 'titre-de-la-publication-1',
                    'publication_datetime' => '2000-01-02T02:03:04+00:00',
                    'cover'                => [
                        '@type' => 'Cover',
                        'cover_url' => 'http://musicall.test/media/cache/resolve/publication_cover_300x300/images/publication/cover/test.jpg',
                    ],
                    'description'          => 'Petite description de la publication 1',
                    'category' => [
                        '@type' => 'Category',
                        'id' => $subCatId,
                        'title' => 'Chroniques',
                        'slug' => 'chroniques',
                    ],
                    'content' => 'find me 1',
                    'thread' => [
                        '@type' => 'Thread',
                        'id' => $threadId,
                    ],
                    'type' => [
                        '@type' => 'Type',
                        'id' => 1,
                        'label' => 'text',
                    ],
                ],
            ],
            'hydra:totalItems' => 2,
            'hydra:view'       => [
                '@id'   => '/api/publications/search?term=find%20me',
                '@type' => 'hydra:PartialCollectionView',
            ],
        ]);
    }
}