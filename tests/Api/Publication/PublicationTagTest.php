<?php

declare(strict_types=1);

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\Publication\TagFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PublicationTagTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_patch_publication_with_tags_persists_them(): void
    {
        $user = UserFactory::new(['username' => 'author_one'])->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'shortDescription' => 'Une description',
            'content' => 'Le contenu',
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'tags' => ['Interview', 'Metal', 'Review'],
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $publication->id,
            '@type' => 'UserPublicationEdit',
            'id' => $publication->id,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'short_description' => 'Une description',
            'content' => 'Le contenu',
            'status_id' => Publication::STATUS_DRAFT,
            'status_label' => 'Brouillon',
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'tags' => ['Interview', 'Metal', 'Review'],
        ]);
    }

    public function test_patch_without_tags_key_preserves_existing_tags(): void
    {
        $user = UserFactory::new(['username' => 'author_two'])->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $tags = [];
        foreach (['A', 'B', 'C'] as $label) {
            $tags[] = TagFactory::createOne(['label' => $label, 'slug' => strtolower($label)]);
        }
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'shortDescription' => 'Une description',
            'content' => 'Le contenu',
            'tags' => new ArrayCollection($tags),
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['title' => 'Edited title']));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $publication->id,
            '@type' => 'UserPublicationEdit',
            'id' => $publication->id,
            'title' => 'Edited title',
            'slug' => 'mon-article',
            'short_description' => 'Une description',
            'content' => 'Le contenu',
            'status_id' => Publication::STATUS_DRAFT,
            'status_label' => 'Brouillon',
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'tags' => ['A', 'B', 'C'],
        ]);
    }

    public function test_patch_with_empty_tags_array_clears_all_tags(): void
    {
        $user = UserFactory::new(['username' => 'author_three'])->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $tags = [];
        foreach (['A', 'B', 'C'] as $label) {
            $tags[] = TagFactory::createOne(['label' => $label, 'slug' => strtolower($label)]);
        }
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'shortDescription' => 'Une description',
            'content' => 'Le contenu',
            'tags' => new ArrayCollection($tags),
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['tags' => []]));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $publication->id,
            '@type' => 'UserPublicationEdit',
            'id' => $publication->id,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'short_description' => 'Une description',
            'content' => 'Le contenu',
            'status_id' => Publication::STATUS_DRAFT,
            'status_label' => 'Brouillon',
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'tags' => [],
        ]);
    }

    public function test_patch_replaces_tag_set(): void
    {
        $user = UserFactory::new(['username' => 'author_four'])->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $tags = [];
        foreach (['A', 'B', 'C'] as $label) {
            $tags[] = TagFactory::createOne(['label' => $label, 'slug' => strtolower($label)]);
        }
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'shortDescription' => 'Une description',
            'content' => 'Le contenu',
            'tags' => new ArrayCollection($tags),
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['tags' => ['B']]));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $publication->id,
            '@type' => 'UserPublicationEdit',
            'id' => $publication->id,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'short_description' => 'Une description',
            'content' => 'Le contenu',
            'status_id' => Publication::STATUS_DRAFT,
            'status_label' => 'Brouillon',
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'tags' => ['B'],
        ]);
    }

    public function test_typeahead_returns_matching_tags(): void
    {
        $metal = TagFactory::createOne(['label' => 'Metal', 'slug' => 'metal']);
        $metalcore = TagFactory::createOne(['label' => 'Metalcore', 'slug' => 'metalcore']);
        TagFactory::createOne(['label' => 'Tour', 'slug' => 'tour']);
        TagFactory::createOne(['label' => 'Interview', 'slug' => 'interview']);

        $this->client->request('GET', '/api/tags?label=Met', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TagSuggestion',
            '@id' => '/api/tags',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/tag_suggestions/' . $metal->slug,
                    '@type' => 'TagSuggestion',
                    'slug' => 'metal',
                    'label' => 'Metal',
                ],
                [
                    '@id' => '/api/tag_suggestions/' . $metalcore->slug,
                    '@type' => 'TagSuggestion',
                    'slug' => 'metalcore',
                    'label' => 'Metalcore',
                ],
            ],
            'view' => [
                '@id' => '/api/tags?label=Met',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/tags{?label}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'label',
                        'property' => 'label',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_typeahead_with_no_label_returns_empty(): void
    {
        TagFactory::createOne(['label' => 'Metal', 'slug' => 'metal']);

        $this->client->request('GET', '/api/tags', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TagSuggestion',
            '@id' => '/api/tags',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/tags{?label}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'label',
                        'property' => 'label',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_filter_publications_by_tag_slug(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'author_filter']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $metal = TagFactory::createOne(['label' => 'Metal', 'slug' => 'metal']);

        $tagged = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'title' => 'Tagged article',
            'slug' => 'tagged-article',
            'shortDescription' => 'Short description',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2026-01-01T00:00:00+00:00'),
            'tags' => new ArrayCollection([$metal]),
        ]);
        PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'title' => 'Untagged article',
            'slug' => 'untagged-article',
            'shortDescription' => 'Other description',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2026-01-02T00:00:00+00:00'),
        ]);

        $this->client->request('GET', '/api/publications?tag.slug=metal', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/publications/tagged-article',
                    '@type' => 'Publication',
                    'id' => $tagged->id,
                    'title' => 'Tagged article',
                    'sub_category' => [
                        '@type' => 'SubCategory',
                        'id' => $category->id,
                        'title' => 'News',
                        'slug' => 'news',
                        'type_label' => 'publication',
                        'is_course' => false,
                    ],
                    'author' => [
                        '@type' => 'Author',
                        'username' => 'author_filter',
                        'deletion_datetime' => null,
                    ],
                    'slug' => 'tagged-article',
                    'publication_datetime' => '2026-01-01T00:00:00+00:00',
                    'cover' => null,
                    'type_label' => 'text',
                    'description' => 'Short description',
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
            ],
            'totalItems' => 1,
            'view' => [
                '@id' => '/api/publications?tag.slug=metal',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/publications{?sub_category.slug,sub_category.type,order[publication_datetime],tag.slug,page}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'sub_category.slug',
                        'property' => 'sub_category.slug',
                        'required' => false,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'sub_category.type',
                        'property' => 'sub_category.type',
                        'required' => false,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'order[publication_datetime]',
                        'property' => 'publication_datetime',
                        'required' => false,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'tag.slug',
                        'property' => 'tag.slug',
                        'required' => false,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'page',
                        'property' => 'page',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }

    public function test_patch_rejects_blank_tag(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user, 'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT, 'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['tags' => ['Valid', '']]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'tags[1]',
                    'message' => 'Le tag ne peut pas être vide',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'tags[1]: Le tag ne peut pas être vide',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'tags[1]: Le tag ne peut pas être vide',
        ]);
    }

    public function test_patch_rejects_overlong_tag(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user, 'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT, 'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['tags' => [str_repeat('a', 101)]]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'tags[0]',
                    'message' => 'Le tag est trop long (max 100 caractères)',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => 'tags[0]: Le tag est trop long (max 100 caractères)',
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'description' => 'tags[0]: Le tag est trop long (max 100 caractères)',
        ]);
    }

    public function test_patch_rejects_too_many_tags(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user, 'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT, 'type' => Publication::TYPE_TEXT,
        ]);

        $tags = array_map(static fn (int $i): string => 'tag-' . $i, range(1, 21));

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['tags' => $tags]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'tags',
                    'message' => 'Un maximum de 20 tags est autorisé',
                    'code' => '756b1212-697c-468d-a9ad-50dd783bb169',
                ],
            ],
            'detail' => 'tags: Un maximum de 20 tags est autorisé',
            'type' => '/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            'title' => 'An error occurred',
            'description' => 'tags: Un maximum de 20 tags est autorisé',
        ]);
    }
}
