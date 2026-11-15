<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceInstrumentFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FeaturedTeacherGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_featured_teachers(): void
    {
        $user1 = UserFactory::new()->create(['username' => 'teacher_alice']);
        $user2 = UserFactory::new()->create(['username' => 'teacher_bob']);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $piano = InstrumentFactory::new()->asPiano()->create();

        $profile1 = ServiceFactory::new()->teaching()
            ->withTrial(0)
            ->create([
                'user' => $user1,
                'creationDatetime' => new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
            ]);

        ServiceInstrumentFactory::new()->create([
            'service' => $profile1,
            'instrument' => $guitar,
        ]);
        ServiceInstrumentFactory::new()->create([
            'service' => $profile1,
            'instrument' => $piano,
        ]);

        $profile2 = ServiceFactory::new()->teaching()->create([
            'user' => $user2,
            'offersTrial' => false,
            'creationDatetime' => new \DateTimeImmutable('2024-02-01T10:00:00+00:00'),
        ]);

        ServiceInstrumentFactory::new()->create([
            'service' => $profile2,
            'instrument' => $piano,
        ]);

        $this->client->request('GET', '/api/teachers/featured');
        $this->assertResponseHeaderSame('content-type', 'application/ld+json');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/FeaturedTeacher',
            '@id'        => '/api/teachers/featured',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'          => '/api/featured_teachers/teacher_alice',
                    '@type'        => 'FeaturedTeacher',
                    'username'     => 'teacher_alice',
                    'instruments'  => [
                        [
                            '@type'           => 'TeacherProfileInstrument',
                            'instrument_id'   => (string) $guitar->id,
                            'instrument_name' => 'Guitare',
                        ],
                        [
                            '@type'           => 'TeacherProfileInstrument',
                            'instrument_id'   => (string) $piano->id,
                            'instrument_name' => 'Piano',
                        ],
                    ],
                    'offers_trial' => true,
                    'trial_price'  => 0,
                ],
                [
                    '@id'          => '/api/featured_teachers/teacher_bob',
                    '@type'        => 'FeaturedTeacher',
                    'username'     => 'teacher_bob',
                    'instruments'  => [
                        [
                            '@type'           => 'TeacherProfileInstrument',
                            'instrument_id'   => (string) $piano->id,
                            'instrument_name' => 'Piano',
                        ],
                    ],
                    'offers_trial' => false,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_featured_teachers_empty(): void
    {
        $this->client->request('GET', '/api/teachers/featured');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/FeaturedTeacher',
            '@id'        => '/api/teachers/featured',
            '@type'      => 'Collection',
            'member'     => [],
            'totalItems' => 0,
        ]);
    }

    public function test_a_username_with_a_dot_is_listed(): void
    {
        $user = UserFactory::new()->create(['username' => 'alice.martin']);
        $profile = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'offersTrial' => false,
            'creationDatetime' => new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
        ]);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        ServiceInstrumentFactory::new()->create(['service' => $profile, 'instrument' => $guitar]);

        $this->client->request('GET', '/api/teachers/featured');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FeaturedTeacher',
            '@id' => '/api/teachers/featured',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/featured_teachers/alice.martin',
                    '@type' => 'FeaturedTeacher',
                    'username' => 'alice.martin',
                    'instruments' => [
                        [
                            '@type' => 'TeacherProfileInstrument',
                            'instrument_id' => (string) $guitar->id,
                            'instrument_name' => 'Guitare',
                        ],
                    ],
                    'offers_trial' => false,
                ],
            ],
            'totalItems' => 1,
        ]);
    }
}
