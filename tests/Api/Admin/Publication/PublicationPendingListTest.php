<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Publication;

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

class PublicationPendingListTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_pending_publications_as_admin(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        PublicationFactory::new([
            'author'      => $admin,
            'status'      => Publication::STATUS_DRAFT,
            'subCategory' => $sub,
        ])->create();
        PublicationFactory::new([
            'author'      => $admin,
            'status'      => Publication::STATUS_ONLINE,
            'subCategory' => $sub,
        ])->create();

        $viewCache = ViewCacheFactory::new(['count' => 123])->create();
        $publication = PublicationFactory::new([
            'author'              => $admin,
            'content'             => 'publication_content',
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'editionDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Petite description de la publication',
            'slug'                => 'titre-de-la-publication',
            'status'              => Publication::STATUS_PENDING,
            'subCategory'         => $sub,
            'title'               => 'Titre de la publication',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => $viewCache,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/publications/pending');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/AdminPendingPublication',
            '@id'        => '/api/admin/publications/pending',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/' . $publication->_real()->getSlug(),
                    '@type'                => 'Publication',
                    'id'                   => $publication->_real()->getId(),
                    'title'                => 'Titre de la publication',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $sub->_real()->getId(),
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $sub->_real()->getId(),
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $admin->getId(),
                        '@type'    => 'User',
                        'username' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'titre-de-la-publication',
                    'publication_datetime' => '2022-01-02T02:03:04+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Petite description de la publication',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_get_pending_publications_not_logged(): void
    {
        $this->client->request('GET', '/api/admin/publications/pending');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_pending_publications_as_normal_user(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/admin/publications/pending');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
