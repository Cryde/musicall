<?php

namespace App\Tests\Api\Admin\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationCoverFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SearchControllerTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_search_publication(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        // not taken because status pending
        PublicationFactory::new([
            'author'              => $admin,
            'status'              => Publication::STATUS_PENDING,
            'title'               => 'Ceci est titre1 publication',
            'type'                => Publication::TYPE_TEXT,
        ])->create()->_real();
        // not taken because not good type
        PublicationFactory::new([
            'author'              => $admin,
            'status'              => Publication::STATUS_ONLINE,
            'title'               => 'Ceci est titre1 publication',
            'type'                => Publication::TYPE_VIDEO,
        ])->create()->_real();

        $publication1 = PublicationFactory::new([
            'author'              => $admin,
            'status'              => Publication::STATUS_ONLINE,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'title'               => 'Ceci est titre1 publication',
            'shortDescription'    => 'Petite description de la publication 1',
            'type'                => Publication::TYPE_TEXT,
        ])->create();

        $cover1 = PublicationCoverFactory::createOne(['imageName' => 'cover1', 'imageSize' => 10, 'publication' => $publication1]);
        $publication1->_real()->setCover($cover1->_real());
        $publication1->_save();

        $publication2 = PublicationFactory::new([
            'author'              => $admin,
            'status'              => Publication::STATUS_ONLINE,
            'title'               => 'Ceci est titre1 publication',
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2030-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Petite description de la publication 2',
            'type'                => Publication::TYPE_TEXT,
        ])->create();

        $cover2 = PublicationCoverFactory::createOne(['imageName' => 'cover2', 'imageSize' => 10, 'publication' => $publication2->_real()]);
        $publication2->_real()->setCover($cover2->_real());
        $publication2->_save();

        $publication1 = $publication1->_real();
        $publication2 = $publication2->_real();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/search/publication?query=titre1');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            [
                'id'          => $publication2->getId(),
                'title'       => 'CECI EST TITRE1 PUBLICATION',
                'description' => 'Petite description de la publication 2',
                'cover_image' => '//images/publication/cover/cover2',
            ],
            [
                'id'          => $publication1->getId(),
                'title'       => 'CECI EST TITRE1 PUBLICATION',
                'description' => 'Petite description de la publication 1',
                'cover_image' => '//images/publication/cover/cover1',
            ],
        ]);
    }
}