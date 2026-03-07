<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\Teacher\TeacherProfileInstrumentFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FeaturedTeacherGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_featured_teachers(): void
    {
        $user1 = UserFactory::new()->create(['username' => 'teacher_alice']);
        $user2 = UserFactory::new()->create(['username' => 'teacher_bob']);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $piano = InstrumentFactory::new()->asPiano()->create();

        $profile1 = TeacherProfileFactory::new()
            ->withTrial(0)
            ->create([
                'user' => $user1,
                'creationDatetime' => new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
            ]);

        TeacherProfileInstrumentFactory::new()->create([
            'teacherProfile' => $profile1,
            'instrument' => $guitar,
        ]);
        TeacherProfileInstrumentFactory::new()->create([
            'teacherProfile' => $profile1,
            'instrument' => $piano,
        ]);

        $profile2 = TeacherProfileFactory::new()->create([
            'user' => $user2,
            'offersTrial' => false,
            'creationDatetime' => new \DateTimeImmutable('2024-02-01T10:00:00+00:00'),
        ]);

        TeacherProfileInstrumentFactory::new()->create([
            'teacherProfile' => $profile2,
            'instrument' => $piano,
        ]);

        $this->client->request('GET', '/api/teachers/featured');
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/FeaturedTeacher',
            '@id'        => '/api/teachers/featured',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'          => '/api/user/profile/teacher_alice/teacher',
                    '@type'        => 'FeaturedTeacher',
                    'username'     => 'teacher_alice',
                    'instruments'  => [
                        [
                            '@type'           => 'TeacherProfileInstrument',
                            'instrument_id'   => (string) $guitar->_real()->getId(),
                            'instrument_name' => 'Guitare',
                        ],
                        [
                            '@type'           => 'TeacherProfileInstrument',
                            'instrument_id'   => (string) $piano->_real()->getId(),
                            'instrument_name' => 'Piano',
                        ],
                    ],
                    'offers_trial' => true,
                    'trial_price'  => 0,
                ],
                [
                    '@id'          => '/api/user/profile/teacher_bob/teacher',
                    '@type'        => 'FeaturedTeacher',
                    'username'     => 'teacher_bob',
                    'instruments'  => [
                        [
                            '@type'           => 'TeacherProfileInstrument',
                            'instrument_id'   => (string) $piano->_real()->getId(),
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
}
