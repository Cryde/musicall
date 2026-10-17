<?php

declare(strict_types=1);

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationCoverFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class PublicationGetTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_item_publication(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $viewCache = ViewCacheFactory::new(['count' => 123])->create();
        $cover = PublicationCoverFactory::new(['imageName' => 'test.jpg'])->create();
        $thread = CommentThreadFactory::new()->create();
        $publication = PublicationFactory::new([
            'author'              => $author,
            'content'             => 'publication_content',
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'editionDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Petite description de la publication',
            'slug'                => 'titre-de-la-publication',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Titre de la publication',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => $viewCache,
            'cover'               => $cover,
            'thread'              => $thread,
        ])->create();
        $this->client->request('GET', '/api/publications/titre-de-la-publication');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications/titre-de-la-publication',
            '@type' => 'Publication',
            'id'                   => $publication->id,
            'title'                => 'Titre de la publication',
            'author'               => [
                '@type'    => 'Author',
                'username' => 'user_admin',
                'display_name' => 'user_admin',
                'deletion_datetime' => null,
            ],
            'slug'                 => 'titre-de-la-publication',
            'content'              => 'publication_content',
            'publication_datetime' => '2022-01-02T02:03:04+00:00',
            'category'             => [
                '@type'    => 'Category',
                'id'       => $sub->id,
                'title'    => 'Chroniques',
                'slug'     => 'chroniques',
                'is_course' => false,
            ],
            'description'          => 'Petite description de la publication',
            'cover'                => [
                '@type'     => 'Cover',
                'cover_url' => 'http://musicall.test/media/cache/resolve/publication_cover_300x300/images/publication/cover/test.jpg',
            ],
            'thread'               => [
                '@type'    => 'Thread',
                'id'       => $thread->id,
            ],
            'type'                 => [
                '@type'    => 'Type',
                'id'       => 1,
                'label'    => 'text',
            ],
            'language'             => 'fr',
            'upvotes'              => 0,
            'downvotes'            => 0,
            'user_vote'            => null,
            'view_count'           => 124,
            'reading_time'         => 1,
            'tags'                 => [],
            'translations'         => [],
        ]);
    }

    public function test_get_item_video_has_no_language(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asDecouvertes()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $cover = PublicationCoverFactory::new(['imageName' => 'video.jpg'])->create();
        $thread = CommentThreadFactory::new()->create();
        $publication = PublicationFactory::new([
            'author'              => $author,
            'content'             => 'video_id',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Description de la video',
            'slug'                => 'titre-de-la-video',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Titre de la video',
            'type'                => Publication::TYPE_VIDEO,
            'cover'               => $cover,
            'thread'              => $thread,
        ])->create();
        $this->client->request('GET', '/api/publications/titre-de-la-video');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications/titre-de-la-video',
            '@type' => 'Publication',
            'id'                   => $publication->id,
            'title'                => 'Titre de la video',
            'author'               => [
                '@type'    => 'Author',
                'username' => 'user_admin',
                'display_name' => 'user_admin',
                'deletion_datetime' => null,
            ],
            'slug'                 => 'titre-de-la-video',
            'content'              => 'video_id',
            'publication_datetime' => '2022-01-02T02:03:04+00:00',
            'category'             => [
                '@type'    => 'Category',
                'id'       => $sub->id,
                'title'    => 'Découvertes',
                'slug'     => 'decouvertes',
                'is_course' => false,
            ],
            'description'          => '',
            'cover'                => [
                '@type'     => 'Cover',
                'cover_url' => 'http://musicall.test/media/cache/resolve/publication_cover_300x300/images/publication/cover/video.jpg',
            ],
            'thread'               => [
                '@type'    => 'Thread',
                'id'       => $thread->id,
            ],
            'type'                 => [
                '@type'    => 'Type',
                'id'       => 2,
                'label'    => 'video',
            ],
            'language'             => null,
            'upvotes'              => 0,
            'downvotes'            => 0,
            'user_vote'            => null,
            'view_count'           => 1,
            'reading_time'         => 0,
            'tags'                 => [],
            'translations'         => [],
        ]);
    }

    public function test_get_item_links_its_online_translations(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $thread = CommentThreadFactory::new()->create();
        $publication = PublicationFactory::new([
            'author'              => $author,
            'content'             => 'contenu',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Description',
            'slug'                => 'mon-article',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Mon article',
            'type'                => Publication::TYPE_TEXT,
            'language'            => 'fr',
            'cover'               => PublicationCoverFactory::new(['imageName' => 'fr.jpg']),
            'thread'              => $thread,
        ])->create();
        $english = PublicationFactory::new([
            'author' => $author, 'slug' => 'my-article', 'status' => Publication::STATUS_ONLINE,
            'subCategory' => $sub, 'type' => Publication::TYPE_TEXT, 'language' => 'en',
        ])->create();
        // Not public yet, so not linked.
        $german = PublicationFactory::new([
            'author' => $author, 'slug' => 'mein-artikel-', 'status' => Publication::STATUS_DRAFT,
            'subCategory' => $sub, 'type' => Publication::TYPE_TEXT, 'language' => 'de',
        ])->create();
        $english->joinTranslationGroupOf($publication);
        $german->joinTranslationGroupOf($publication);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->request('GET', '/api/publications/mon-article');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications/mon-article',
            '@type' => 'Publication',
            'id'                   => $publication->id,
            'title'                => 'Mon article',
            'author'               => [
                '@type'    => 'Author',
                'username' => 'user_admin',
                'display_name' => 'user_admin',
                'deletion_datetime' => null,
            ],
            'slug'                 => 'mon-article',
            'content'              => 'contenu',
            'publication_datetime' => '2022-01-02T02:03:04+00:00',
            'category'             => [
                '@type'    => 'Category',
                'id'       => $sub->id,
                'title'    => 'Chroniques',
                'slug'     => 'chroniques',
                'is_course' => false,
            ],
            'description'          => 'Description',
            'cover'                => [
                '@type'     => 'Cover',
                'cover_url' => 'http://musicall.test/media/cache/resolve/publication_cover_300x300/images/publication/cover/fr.jpg',
            ],
            'thread'               => [
                '@type'    => 'Thread',
                'id'       => $thread->id,
            ],
            'type'                 => [
                '@type'    => 'Type',
                'id'       => 1,
                'label'    => 'text',
            ],
            'language'             => 'fr',
            'upvotes'              => 0,
            'downvotes'            => 0,
            'user_vote'            => null,
            'view_count'           => 1,
            'reading_time'         => 1,
            'tags'                 => [],
            'translations'         => [
                [
                    '@type'    => 'Translation',
                    'language' => 'en',
                    'slug'     => 'my-article',
                ],
            ],
        ]);
    }

    public function test_get_item_publication_not_found(): void
    {
        $this->client->request('GET', '/api/publications/not_found');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title'       => 'An error occurred',
            'description' => 'Publication inexistante',
            'detail'            => 'Publication inexistante',
            'status'            => 404,
            'type'              => '/errors/404',
            '@context' => '/api/contexts/Error',
        ]);
    }
}
