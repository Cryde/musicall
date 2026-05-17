<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Publication;

use App\Entity\Publication;
use App\Entity\Publication\Tag;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\Publication\TagFactory;
use App\Repository\Publication\TagRepository;
use App\Repository\PublicationRepository;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AdminTagTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_list_unauthenticated_returns_401(): void
    {
        $this->client->jsonRequest('GET', '/api/admin/tags', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_list_as_base_user_returns_403(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/admin/tags', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'status' => 403,
            'type' => '/errors/403',
        ]);
    }

    public function test_list_returns_tags_with_publication_count(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $metalCreatedAt = \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2026-01-01T00:00:00+00:00');
        $tourCreatedAt = \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2026-01-02T00:00:00+00:00');
        $metal = TagFactory::createOne(['label' => 'Metal', 'slug' => 'metal', 'creationDatetime' => $metalCreatedAt]);
        $tour = TagFactory::createOne(['label' => 'Tour', 'slug' => 'tour', 'creationDatetime' => $tourCreatedAt]);

        PublicationFactory::new()->create([
            'author' => $admin,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'tags' => new ArrayCollection([$metal]),
        ]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('GET', '/api/admin/tags', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminTag',
            '@id' => '/api/admin/tags',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/admin/tags/' . $metal->id,
                    '@type' => 'AdminTag',
                    'id' => $metal->id,
                    'label' => 'Metal',
                    'slug' => 'metal',
                    'creation_datetime' => '2026-01-01T00:00:00+00:00',
                    'publication_count' => 1,
                ],
                [
                    '@id' => '/api/admin/tags/' . $tour->id,
                    '@type' => 'AdminTag',
                    'id' => $tour->id,
                    'label' => 'Tour',
                    'slug' => 'tour',
                    'creation_datetime' => '2026-01-02T00:00:00+00:00',
                    'publication_count' => 0,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_create_new_tag_succeeds(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/tags',
            ['label' => 'Metalcore'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $tagRepo = static::getContainer()->get(\App\Repository\Publication\TagRepository::class);
        $created = $tagRepo->findOneBySlug('metalcore');
        $this->assertInstanceOf(Tag::class, $created);

        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminTag',
            '@id' => '/api/admin/tags/' . $created->id,
            '@type' => 'AdminTag',
            'id' => $created->id,
            'label' => 'Metalcore',
            'slug' => 'metalcore',
            'creation_datetime' => $created->creationDatetime->format(\DateTimeInterface::ATOM),
            'publication_count' => 0,
        ]);
        $this->assertCount(1, $tagRepo->findAll());
    }

    public function test_create_existing_tag_is_idempotent(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $existingCreatedAt = \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2026-01-01T00:00:00+00:00');
        $existing = TagFactory::createOne([
            'label' => 'Metal',
            'slug' => 'metal',
            'creationDatetime' => $existingCreatedAt,
        ]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/tags',
            ['label' => 'metal'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminTag',
            '@id' => '/api/admin/tags/' . $existing->id,
            '@type' => 'AdminTag',
            'id' => $existing->id,
            'label' => 'Metal',
            'slug' => 'metal',
            'creation_datetime' => '2026-01-01T00:00:00+00:00',
            'publication_count' => 0,
        ]);

        $tagRepo = static::getContainer()->get(\App\Repository\Publication\TagRepository::class);
        $this->assertCount(1, $tagRepo->findAll(), 'Same slug should not create a duplicate');
    }

    public function test_create_existing_tag_returns_real_publication_count(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $existingCreatedAt = \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2026-01-01T00:00:00+00:00');
        $existing = TagFactory::createOne([
            'label' => 'Metal',
            'slug' => 'metal',
            'creationDatetime' => $existingCreatedAt,
        ]);
        PublicationFactory::new()->create([
            'author' => $admin,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'tags' => new ArrayCollection([$existing]),
        ]);
        PublicationFactory::new()->create([
            'author' => $admin,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'tags' => new ArrayCollection([$existing]),
        ]);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/tags',
            ['label' => 'metal'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminTag',
            '@id' => '/api/admin/tags/' . $existing->id,
            '@type' => 'AdminTag',
            'id' => $existing->id,
            'label' => 'Metal',
            'slug' => 'metal',
            'creation_datetime' => '2026-01-01T00:00:00+00:00',
            'publication_count' => 2,
        ]);
    }

    public function test_create_with_blank_label_returns_422(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/tags',
            ['label' => ''],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'label',
                    'message' => 'Le label ne peut pas être vide',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'label',
                    'message' => 'Cette chaîne est trop courte. Elle doit avoir au minimum 1 caractère.',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
            'detail' => "label: Le label ne peut pas être vide\nlabel: Cette chaîne est trop courte. Elle doit avoir au minimum 1 caractère.",
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
            'description' => "label: Le label ne peut pas être vide\nlabel: Cette chaîne est trop courte. Elle doit avoir au minimum 1 caractère.",
        ]);
    }

    public function test_delete_tag_cascades_m2m(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $tag = TagFactory::createOne(['label' => 'Metal', 'slug' => 'metal']);

        $pub = PublicationFactory::new()->create([
            'author' => $admin,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'tags' => new ArrayCollection([$tag]),
        ]);
        $tagId = $tag->id;
        $pubId = $pub->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest('DELETE', '/api/admin/tags/' . $tagId, [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $tagRepo = static::getContainer()->get(TagRepository::class);
        $publicationRepo = static::getContainer()->get(PublicationRepository::class);

        $this->assertNull($tagRepo->find($tagId));
        $this->assertCount(0, $publicationRepo->find($pubId)->tags);
    }

    public function test_delete_unknown_id_returns_404(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $this->client->loginUser($admin);
        $this->client->jsonRequest('DELETE', '/api/admin/tags/9999999', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tag not found',
            'description' => 'Tag not found',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_delete_as_base_user_returns_403(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $tag = TagFactory::createOne(['label' => 'X', 'slug' => 'x']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('DELETE', '/api/admin/tags/' . $tag->id, [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'status' => 403,
            'type' => '/errors/403',
        ]);
    }
}
