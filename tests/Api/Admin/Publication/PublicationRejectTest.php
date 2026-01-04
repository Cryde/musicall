<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Publication;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicationRejectTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_reject_publication(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        // this one should be kept as DRAFT :
        $draft = PublicationFactory::new(['author' => $admin, 'status' => Publication::STATUS_DRAFT, 'subCategory' => $sub])->create();

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
        ])->create();

        $this->assertSame(Publication::STATUS_PENDING, $publication->getStatus());
        $this->assertNull($publication->getPublicationDatetime());

        $publicationId = $publication->getId();
        $draftId = $draft->getId();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/publications/' . $publicationId . '/reject', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        /** @var PublicationRepository $publicationRepository */
        $publicationRepository = static::getContainer()->get(PublicationRepository::class);
        $refreshedDraft = $publicationRepository->find($draftId);
        $refreshedPublication = $publicationRepository->find($publicationId);

        $this->assertSame(Publication::STATUS_DRAFT, $refreshedDraft->getStatus());
        $this->assertSame(Publication::STATUS_DRAFT, $refreshedPublication->getStatus());
        $this->assertNull($refreshedPublication->getPublicationDatetime());
    }

    public function test_reject_publication_with_no_admin(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $publication = PublicationFactory::new(['author' => $user, 'status' => Publication::STATUS_PENDING, 'subCategory' => $sub])->create();

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/admin/publications/' . $publication->getId() . '/reject', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_reject_publication_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $publication = PublicationFactory::new(['author' => $user, 'status' => Publication::STATUS_PENDING, 'subCategory' => $sub])->create();

        $this->client->request('POST', '/api/admin/publications/' . $publication->getId() . '/reject', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
