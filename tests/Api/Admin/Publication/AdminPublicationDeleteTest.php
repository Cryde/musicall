<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Publication;

use App\Entity\Image\PublicationCover;
use App\Entity\Image\PublicationImage;
use App\Entity\Publication;
use App\Repository\Comment\CommentRepository;
use App\Repository\Comment\CommentThreadRepository;
use App\Repository\Metric\ViewCacheRepository;
use App\Repository\Metric\ViewRepository;
use App\Repository\Metric\VoteCacheRepository;
use App\Repository\Metric\VoteRepository;
use App\Repository\Publication\TagRepository;
use App\Repository\PublicationRepository;
use App\Repository\UserRepository;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
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
        $em = self::getContainer()->get(EntityManagerInterface::class);
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
        $tagId = $tag->id;
        $authorId = $author->id;

        $publicationRepo = self::getContainer()->get(PublicationRepository::class);
        $viewCacheRepo = self::getContainer()->get(ViewCacheRepository::class);
        $voteCacheRepo = self::getContainer()->get(VoteCacheRepository::class);
        $viewRepo = self::getContainer()->get(ViewRepository::class);
        $voteRepo = self::getContainer()->get(VoteRepository::class);
        $commentRepo = self::getContainer()->get(CommentRepository::class);
        $commentThreadRepo = self::getContainer()->get(CommentThreadRepository::class);
        $tagRepo = self::getContainer()->get(TagRepository::class);
        $userRepo = self::getContainer()->get(UserRepository::class);
        $coverRepo = $em->getRepository(PublicationCover::class);
        $imageRepo = $em->getRepository(PublicationImage::class);

        // Sanity: rows are present before the delete.
        self::assertSame(2, $viewRepo->count(['viewCache' => $viewCacheId]));
        self::assertSame(2, $voteRepo->count(['voteCache' => $voteCacheId]));
        self::assertSame(2, $commentRepo->count(['thread' => $threadId]));
        self::assertSame(1, $tagRepo->countPublicationsForTag($tagId));

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/admin/publications/' . $publicationId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Detach managed entities so the assertions below hit the DB, not the identity map.
        $em->clear();

        // Publication itself is gone.
        self::assertNull($publicationRepo->find($publicationId));
        // Cover (via Doctrine cascade).
        self::assertNull($coverRepo->find($coverId));
        // Cached metrics gone.
        self::assertNull($viewCacheRepo->find($viewCacheId));
        self::assertNull($voteCacheRepo->find($voteCacheId));
        // Granular metric rows gone via DB FK cascade.
        self::assertSame(0, $viewRepo->count(['viewCache' => $viewCacheId]));
        self::assertSame(0, $voteRepo->count(['voteCache' => $voteCacheId]));
        // Images gone.
        foreach ($imageIds as $id) {
            self::assertNull($imageRepo->find($id));
        }
        // Comments gone, with their VoteCaches.
        foreach ($commentIds as $id) {
            self::assertNull($commentRepo->find($id));
        }
        self::assertNull($voteCacheRepo->find($commentVoteCache1Id));
        self::assertNull($voteCacheRepo->find($commentVoteCache2Id));
        // Thread gone.
        self::assertNull($commentThreadRepo->find($threadId));
        // M2M tag links gone (TagRepository::countPublicationsForTag counts map_publication_tag
        // rows for this tag), but the Tag itself preserved.
        self::assertSame(0, $tagRepo->countPublicationsForTag($tagId));
        self::assertNotNull($tagRepo->find($tagId));
        // Author preserved.
        self::assertNotNull($userRepo->find($authorId));
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
}
