<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Profile;

use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceOfferFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The services a profile lists (#1415), next to the teacher fields installed app builds read. */
#[ResetDatabase]
class PublicProfileServicesTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_profile_lists_its_services_in_the_members_order(): void
    {
        $user = $this->member('julien');
        // Created first, placed second: the member's order wins.
        $teaching = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'headline' => 'Cours de guitare',
            'position' => 1,
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ]);
        ServiceOfferFactory::new()->package(10, 25000)->create(['service' => $teaching]);
        ServiceOfferFactory::new()->lesson(60, 3000)->create(['service' => $teaching]);
        $mixing = ServiceFactory::new()->soundEngineering()->create([
            'user' => $user,
            'headline' => 'Mixage et prise de son',
            'position' => 0,
            'creationDatetime' => new \DateTimeImmutable('2025-03-01T10:00:00+00:00'),
        ]);
        // Cheaper by the hour, but a sound engineer is quoted by the track.
        ServiceOfferFactory::new()->create(['service' => $mixing, 'unit' => OfferUnit::Hour, 'quantity' => null, 'price' => 4000, 'title' => 'Une heure de studio']);
        ServiceOfferFactory::new()->create(['service' => $mixing, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 8000, 'title' => "Mixage d'un titre"]);
        ServiceFactory::new()->soundEngineering()->create(['headline' => 'Pas le sien']);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $this->client->request('GET', '/api/user/profile/julien');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/julien',
            '@type' => 'PublicProfile',
            'username' => 'julien',
            'user_id' => $user->id,
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => false,
            'has_teacher_profile' => true,
            'teacher' => [
                '@type' => 'PublicProfileTeacher',
                'instruments' => [],
                'offers_trial' => false,
                'pricing' => [
                    ['@type' => 'TeacherProfilePricing', 'duration' => '1h', 'price' => 3000],
                ],
                'media' => [],
                'availability' => [],
            ],
            'services' => [
                [
                    '@type' => 'PublicProfileService',
                    'id' => $mixing->id,
                    'kind' => 'sound_engineering',
                    'status' => 'published',
                    'headline' => 'Mixage et prise de son',
                    'url' => 'http://localhost/u/julien/sound-engineer',
                    'price' => 8000,
                    'price_unit' => 'track',
                ],
                [
                    '@type' => 'PublicProfileService',
                    'id' => $teaching->id,
                    'kind' => 'teaching',
                    'status' => 'published',
                    'headline' => 'Cours de guitare',
                    'url' => 'http://localhost/u/julien/teacher',
                    // A lesson, never the package: a package's price covers several lessons.
                    'price' => 3000,
                    'price_unit' => 'lesson',
                    'price_quantity' => 60,
                ],
            ],
        ]);
    }

    /** A service is in the reader's locale: its address too. */
    public function test_an_english_reader_gets_the_english_address(): void
    {
        $user = $this->member('julien');
        $mixing = ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'headline' => 'Mixing']);

        $this->client->request('GET', '/api/user/profile/julien', server: ['HTTP_X_LOCALE' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/julien',
            '@type' => 'PublicProfile',
            'username' => 'julien',
            'user_id' => $user->id,
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
            'services' => [
                [
                    '@type' => 'PublicProfileService',
                    'id' => $mixing->id,
                    'kind' => 'sound_engineering',
                    'status' => 'published',
                    'headline' => 'Mixing',
                    'url' => 'http://localhost/en/u/julien/sound-engineer',
                ],
            ],
        ]);
    }

    public function test_a_draft_is_left_out_for_a_visitor(): void
    {
        [$user, $mixing] = $this->publishedMixingAndDraftTeaching();

        $this->client->request('GET', '/api/user/profile/julien');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/julien',
            '@type' => 'PublicProfile',
            'username' => 'julien',
            'user_id' => $user->id,
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
            'services' => [
                [
                    '@type' => 'PublicProfileService',
                    'id' => $mixing->id,
                    'kind' => 'sound_engineering',
                    'status' => 'published',
                    'headline' => 'Mixage et prise de son',
                    'url' => 'http://localhost/u/julien/sound-engineer',
                    // No price by the track: the cheapest of any unit.
                    'price' => 4000,
                    'price_unit' => 'hour',
                ],
            ],
        ]);
    }

    /** Its owner sees their drafts in their place, marked, so the page can say « Brouillon ». A draft is still no teacher profile. */
    public function test_its_owner_also_sees_their_drafts(): void
    {
        [$user, $mixing, $teaching] = $this->publishedMixingAndDraftTeaching();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/profile/julien');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/julien',
            '@type' => 'PublicProfile',
            'username' => 'julien',
            'user_id' => $user->id,
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'has_posts' => false,
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
            'services' => [
                [
                    '@type' => 'PublicProfileService',
                    'id' => $teaching->id,
                    'kind' => 'teaching',
                    'status' => 'draft',
                    'url' => 'http://localhost/u/julien/teacher',
                ],
                [
                    '@type' => 'PublicProfileService',
                    'id' => $mixing->id,
                    'kind' => 'sound_engineering',
                    'status' => 'published',
                    'headline' => 'Mixage et prise de son',
                    'url' => 'http://localhost/u/julien/sound-engineer',
                    'price' => 4000,
                    'price_unit' => 'hour',
                ],
            ],
        ]);
    }

    private function member(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }

    /** @return array{User, Service, Service} julien, his published mixing, and his teaching draft placed before it */
    private function publishedMixingAndDraftTeaching(): array
    {
        $user = $this->member('julien');
        $mixing = ServiceFactory::new()->soundEngineering()->create([
            'user' => $user,
            'headline' => 'Mixage et prise de son',
            'position' => 1,
        ]);
        ServiceOfferFactory::new()->create(['service' => $mixing, 'unit' => OfferUnit::Hour, 'quantity' => null, 'price' => 4000, 'title' => 'Une heure de studio']);
        $teaching = ServiceFactory::new()->teaching()->create([
            'user' => $user,
            'status' => ServiceStatus::Draft,
            'position' => 0,
        ]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return [$user, $mixing, $teaching];
    }
}
