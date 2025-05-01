<?php

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

class PublicationApproveTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_approve_publication(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        // this one should be kept as DRAFT :
        $draft = PublicationFactory::new(['author' => $admin, 'status' => Publication::STATUS_DRAFT, 'subCategory' => $sub,])->create();

        $viewCache = ViewCacheFactory::new(['count' => 123])->create();
        $publication = PublicationFactory::new([
            'author'              => $admin,
            'content'             => 'publication_content',
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'editionDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => null,
            'shortDescription'    => 'Petite description de la publication',
            'slug'                => 'titre-de-la-publication',
            'status'              => Publication::STATUS_PENDING,
            'subCategory'         => $sub,
            'title'               => 'Titre de la publication',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => $viewCache,
        ])->create()->_real();

        $this->assertSame(2, $publication->getStatus());
        $this->assertNull($publication->getPublicationDatetime());

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/publications/' . $publication->getId() . '/approve');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([]);

        $this->assertSame(0, $draft->getStatus());
        $this->assertSame(1, $publication->getStatus());
        $this->assertNotNull($publication->getPublicationDatetime());
    }

    public function test_approve_publication_with_no_admin(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        // this one should be kept as DRAFT :
        $publication = PublicationFactory::new(['author' => $user, 'status' => Publication::STATUS_DRAFT, 'subCategory' => $sub,])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/publications/' . $publication->getId() . '/approve');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}