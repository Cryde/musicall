<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Comment\CommentFactory;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Metric\ViewFactory;
use App\Tests\Factory\Metric\VoteCacheFactory;
use App\Tests\Factory\Metric\VoteFactory;
use App\Tests\Factory\Publication\PublicationCoverFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationImageFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\Publication\TagFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class AdminPublicationDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_publication_cascades_every_related_row(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asBaseUser()->create();
        $admin = UserFactory::new()->asAdminUser()->create();

        $viewCache = ViewCacheFactory::new(['count' => 7])->create();
        $voteCache = VoteCacheFactory::new(['upvoteCount' => 3, 'downvoteCount' => 1])->create();
        $cover = PublicationCoverFactory::new(['imageName' => 'pub-to-delete.jpg'])->create();
        $thread = CommentThreadFactory::new()->create();
        $tag = TagFactory::new(['label' => 'Metal', 'slug' => 'metal'])->create();

        $publication = PublicationFactory::new([
            'author'              => $author,
            'content'             => 'doomed content',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T00:00:00+00:00'),
            'shortDescription'    => 'doomed description',
            'slug'                => 'doomed-publication',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Doomed Publication',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => $viewCache,
            'voteCache'           => $voteCache,
            'cover'               => $cover,
            'thread'              => $thread,
            'tags'                => new ArrayCollection([$tag]),
        ])->create();
        $publicationId = $publication->id;

        // Mirror what PublicationVideoCreationProcedure does in prod: set the cover's back-ref
        // to the publication. This is what makes the OneToOne bi-directional and triggers
        // Doctrine's CycleDetectedException unless the procedure handles it.
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $cover->publication = $publication;
        $em->flush();

        $image1 = PublicationImageFactory::new(['publication' => $publication, 'imageName' => 'img-1.jpg'])->create();
        $image2 = PublicationImageFactory::new(['publication' => $publication, 'imageName' => 'img-2.jpg'])->create();
        $imageIds = [$image1->id, $image2->id];

        // Two View rows attached to the publication's ViewCache.
        ViewFactory::new(['viewCache' => $viewCache, 'identifier' => 'anon-a', 'entityType' => 'app_publication', 'entityId' => (string) $publicationId])->create();
        ViewFactory::new(['viewCache' => $viewCache, 'identifier' => 'anon-b', 'entityType' => 'app_publication', 'entityId' => (string) $publicationId])->create();

        // Two Vote rows attached to the publication's VoteCache.
        VoteFactory::new(['voteCache' => $voteCache, 'user' => $author, 'identifier' => 'anon-a', 'value' => 1, 'entityType' => 'app_publication', 'entityId' => (string) $publicationId])->create();
        VoteFactory::new(['voteCache' => $voteCache, 'user' => $admin, 'identifier' => 'anon-b', 'value' => -1, 'entityType' => 'app_publication', 'entityId' => (string) $publicationId])->create();

        // Two comments, each with its own VoteCache and one Vote row.
        $commentVoteCache1 = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $commentVoteCache2 = VoteCacheFactory::new(['upvoteCount' => 0, 'downvoteCount' => 0])->create();
        $comment1 = CommentFactory::new(['thread' => $thread, 'author' => $author, 'content' => 'nice', 'voteCache' => $commentVoteCache1])->create();
        $comment2 = CommentFactory::new(['thread' => $thread, 'author' => $author, 'content' => 'okay', 'voteCache' => $commentVoteCache2])->create();
        $commentIds = [$comment1->id, $comment2->id];
        VoteFactory::new(['voteCache' => $commentVoteCache1, 'user' => $admin, 'identifier' => 'anon-a', 'value' => 1, 'entityType' => 'app_comment', 'entityId' => (string) $comment1->id])->create();

        $threadId = $thread->id;
        $viewCacheId = $viewCache->id;
        $voteCacheId = $voteCache->id;
        $coverId = $cover->id;
        $commentVoteCache1Id = $commentVoteCache1->id;
        $commentVoteCache2Id = $commentVoteCache2->id;

        // Sanity: rows are present before the delete.
        $conn = $this->getDbConnection();
        self::assertSame(2, $this->countViewRowsForCache($conn, $viewCacheId));
        self::assertSame(2, $this->countVoteRowsForCache($conn, $voteCacheId));
        self::assertSame(2, $this->countComments($conn, $threadId));
        self::assertSame(1, $this->countMapTag($conn, $publicationId));

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/admin/publications/' . $publicationId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Publication itself is gone.
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM publication WHERE id = ?', [$publicationId]));
        // Cover (via Doctrine cascade).
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM publication_cover WHERE id = ?', [$coverId]));
        // Cached metrics gone.
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM view_cache WHERE id = ?', [$viewCacheId]));
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM vote_cache WHERE id = ?', [$voteCacheId]));
        // Granular metric rows gone via DB FK cascade.
        self::assertSame(0, $this->countViewRowsForCache($conn, $viewCacheId));
        self::assertSame(0, $this->countVoteRowsForCache($conn, $voteCacheId));
        // Images gone.
        foreach ($imageIds as $id) {
            self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM publication_image WHERE id = ?', [$id]));
        }
        // Comments gone, with their VoteCaches.
        foreach ($commentIds as $id) {
            self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM comment WHERE id = ?', [$id]));
        }
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM vote_cache WHERE id = ?', [$commentVoteCache1Id]));
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM vote_cache WHERE id = ?', [$commentVoteCache2Id]));
        // Thread gone.
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM comment_thread WHERE id = ?', [$threadId]));
        // M2M tag links gone, but the Tag itself preserved.
        self::assertSame(0, $this->countMapTag($conn, $publicationId));
        self::assertSame(1, (int) $conn->fetchOne('SELECT COUNT(*) FROM tag WHERE id = ?', [$tag->id]));
        // Author preserved.
        self::assertSame(1, (int) $conn->fetchOne('SELECT COUNT(*) FROM fos_user WHERE id = ?', [$author->id]));
    }

    public function test_delete_publication_returns_401_when_anonymous(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asBaseUser()->create();
        $publication = PublicationFactory::new([
            'author'              => $author,
            'slug'                => 'untouchable',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Untouchable',
            'type'                => Publication::TYPE_TEXT,
        ])->create();

        $this->client->request('DELETE', '/api/admin/publications/' . $publication->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_delete_publication_returns_403_when_not_admin(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asBaseUser()->create();
        $publication = PublicationFactory::new([
            'author'              => $author,
            'slug'                => 'untouchable',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Untouchable',
            'type'                => Publication::TYPE_TEXT,
        ])->create();

        $this->client->loginUser($author);
        $this->client->request('DELETE', '/api/admin/publications/' . $publication->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context'    => '/api/contexts/Error',
            '@id'         => '/api/errors/403',
            '@type'       => 'Error',
            'status'      => 403,
            'type'        => '/errors/403',
            'title'       => 'An error occurred',
            'detail'      => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
        ]);
    }

    public function test_delete_publication_returns_404_when_missing(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/admin/publications/9999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context'    => '/api/contexts/Error',
            '@id'         => '/api/errors/404',
            '@type'       => 'Error',
            'status'      => 404,
            'type'        => '/errors/404',
            'title'       => 'An error occurred',
            'detail'      => 'Publication not found',
            'description' => 'Publication not found',
        ]);
    }

    private function getDbConnection(): Connection
    {
        return self::getContainer()->get('doctrine.orm.entity_manager')->getConnection();
    }

    private function countViewRowsForCache(Connection $conn, int $viewCacheId): int
    {
        return (int) $conn->fetchOne('SELECT COUNT(*) FROM view WHERE view_cache_id = ?', [$viewCacheId]);
    }

    private function countVoteRowsForCache(Connection $conn, int $voteCacheId): int
    {
        return (int) $conn->fetchOne('SELECT COUNT(*) FROM vote WHERE vote_cache_id = ?', [$voteCacheId]);
    }

    private function countComments(Connection $conn, int $threadId): int
    {
        return (int) $conn->fetchOne('SELECT COUNT(*) FROM comment WHERE thread_id = ?', [$threadId]);
    }

    private function countMapTag(Connection $conn, int $publicationId): int
    {
        return (int) $conn->fetchOne('SELECT COUNT(*) FROM map_publication_tag WHERE publication_id = ?', [$publicationId]);
    }
}
