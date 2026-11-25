<?php

declare(strict_types=1);

namespace App\Tests\Api\Service;

use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Musician\MediaPlatform;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceMediaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** A draft is seen by its owner only (#1406): never in a search, the annuaire, the profile or the wall. */
#[ResetDatabase]
class ServiceDraftVisibilityTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_draft_teacher_page_is_not_found_for_a_visitor(): void
    {
        $this->draftTeaching($this->member('draft_teacher'));

        $this->client->request('GET', '/api/user/profile/draft_teacher/teacher');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Profil professeur non trouvé',
            'description' => 'Profil professeur non trouvé',
        ]);
    }

    public function test_a_draft_teacher_page_is_not_found_for_another_member(): void
    {
        $this->draftTeaching($this->member('draft_teacher'));

        $this->client->loginUser($this->member('someone_else'));
        $this->client->request('GET', '/api/user/profile/draft_teacher/teacher');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Profil professeur non trouvé',
            'description' => 'Profil professeur non trouvé',
        ]);
    }

    public function test_a_draft_teacher_page_is_shown_to_its_owner_as_a_draft(): void
    {
        $owner = $this->member('draft_teacher');
        $this->draftTeaching($owner);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/api/user/profile/draft_teacher/teacher');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfile',
            '@id' => '/api/user/profile/draft_teacher/teacher',
            '@type' => 'TeacherProfile',
            'username' => 'draft_teacher',
            'user_id' => $owner->id,
            'display_name' => 'draft_teacher',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'status' => 'draft',
            'musician_instruments' => [],
            'musician_bands' => [],
            'description' => 'Cours de guitare',
            'student_levels' => [],
            'age_groups' => [],
            'offers_trial' => false,
            'locations' => [],
            'instruments' => [],
            'styles' => [],
            'media' => [],
            'pricing' => [],
            'availability' => [],
            'packages' => [],
            'social_links' => [],
            'level_goals' => [],
            'songs' => [],
            'creation_datetime' => '2024-01-15T10:00:00+00:00',
        ]);
    }

    public function test_a_draft_is_not_on_the_profile(): void
    {
        $owner = $this->member('draft_teacher');
        $this->draftTeaching($owner);

        $this->client->request('GET', '/api/user/profile/draft_teacher');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/draft_teacher',
            '@type' => 'PublicProfile',
            'username' => 'draft_teacher',
            'user_id' => $owner->id,
            'member_since' => '1990-01-02T02:03:04+00:00',
            'is_public' => true,
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => false,
            'has_teacher_profile' => false,
            'services' => [],
        ]);
    }

    public function test_a_draft_is_not_among_the_featured_teachers(): void
    {
        $this->draftTeaching($this->member('draft_teacher'));

        $this->client->request('GET', '/api/teachers/featured');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FeaturedTeacher',
            '@id' => '/api/teachers/featured',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_a_drafts_media_is_not_on_the_wall(): void
    {
        $service = $this->draftTeaching($this->member('draft_teacher'));
        ServiceMediaFactory::new()->create([
            'service' => $service,
            'platform' => MediaPlatform::SOUNDCLOUD,
            'url' => 'https://soundcloud.com/draft/arpeges',
            'embedId' => 'draft/arpeges',
            'title' => 'Arpèges',
            'creationDatetime' => new \DateTimeImmutable('2026-10-01T10:00:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/draft_teacher/wall?type=media');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/draft_teacher/wall',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => [
                '@id' => '/api/user/profile/draft_teacher/wall?type=media',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    private function member(string $username): User
    {
        return UserFactory::new()->create([
            'username' => $username,
            'email' => $username . '@test.com',
            'creationDatetime' => new \DateTime('1990-01-02T02:03:04+00:00'),
        ]);
    }

    private function draftTeaching(User $owner): Service
    {
        return ServiceFactory::new()->teaching()->create([
            'user' => $owner,
            'status' => ServiceStatus::Draft,
            'description' => 'Cours de guitare',
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ]);
    }
}
