<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\TechRiderSection;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TechRiderSectionRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderSectionFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\TechRider\TechRiderSectionContent;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TechRiderSectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    private const array PATCH_HEADERS = [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    private const array SAMPLE_CONTENT = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Rack near the drums, 3m snakes.']],
            ],
        ],
    ];

    public function test_create_section(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        TechRiderSectionFactory::new(['techRider' => $rider, 'title' => 'Existant', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->sectionsUrl($bandSpace->id, $rider->id),
            ['title' => 'Sonorisation', 'content' => self::SAMPLE_CONTENT],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $sections = self::getContainer()->get(TechRiderSectionRepository::class)->findByRider($rider);
        $this->assertCount(2, $sections);
        $created = $sections[1];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderSection',
            '@id' => $this->sectionsUrl($bandSpace->id, $rider->id) . '/' . $created->id,
            '@type' => 'TechRiderSection',
            'id' => (string) $created->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'title' => 'Sonorisation',
            'content' => self::SAMPLE_CONTENT,
            // Appended after the existing section rather than inserted at the front.
            'position' => 1,
            'creation_datetime' => $created->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $created->id);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_section_added', $activities[0]->type);
        $this->assertSame(['rider_name' => 'Rider', 'title' => 'Sonorisation'], $activities[0]->payload);
    }

    public function test_create_section_title_required(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', $this->sectionsUrl($bandSpace->id, $rider->id), ['title' => ''], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'title',
                    'message' => 'Veuillez spécifier un titre',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'title: Veuillez spécifier un titre',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'title: Veuillez spécifier un titre',
        ]);
    }

    public function test_create_section_content_too_large(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $oversized = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => str_repeat('a', TechRiderSectionContent::MAX_CONTENT_BYTES)]],
            ]],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->sectionsUrl($bandSpace->id, $rider->id),
            ['title' => 'Trop long', 'content' => $oversized],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderSectionContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le contenu de la section est trop volumineux (limite : 200 Ko)',
                    'code' => TechRiderSectionContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Le contenu de la section est trop volumineux (limite : 200 Ko)',
            'type' => '/validation_errors/' . TechRiderSectionContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Le contenu de la section est trop volumineux (limite : 200 Ko)',
        ]);
    }

    /**
     * The cap has to hold on PATCH too, which is the path the autosaving editor actually
     * uses. It runs against the merged resource rather than a bare input DTO, so it is a
     * different route through the same constraint.
     */
    public function test_patch_content_too_large_is_rejected_and_changes_nothing(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $section = TechRiderSectionFactory::new([
            'techRider' => $rider,
            'title' => 'Sonorisation',
            'content' => self::SAMPLE_CONTENT,
        ])->create();

        $oversized = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => str_repeat('a', TechRiderSectionContent::MAX_CONTENT_BYTES)]],
            ]],
        ];

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->sectionsUrl($bandSpace->id, $rider->id) . '/' . $section->id,
            ['content' => $oversized],
            self::PATCH_HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . TechRiderSectionContent::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le contenu de la section est trop volumineux (limite : 200 Ko)',
                    'code' => TechRiderSectionContent::ERROR_CODE,
                ],
            ],
            'detail' => 'content: Le contenu de la section est trop volumineux (limite : 200 Ko)',
            'type' => '/validation_errors/' . TechRiderSectionContent::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'content: Le contenu de la section est trop volumineux (limite : 200 Ko)',
        ]);

        $stored = self::getContainer()->get(TechRiderSectionRepository::class)
            ->findOneByIdAndRider((string) $section->id, $rider);
        $this->assertSame(self::SAMPLE_CONTENT, $stored?->content);
    }

    /**
     * The regression the raw-payload handling exists to prevent: a rename must not wipe what
     * the section already says.
     */
    public function test_patch_title_only_leaves_content_untouched(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $section = TechRiderSectionFactory::new([
            'techRider' => $rider,
            'title' => 'Sonorisation',
            'content' => self::SAMPLE_CONTENT,
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->sectionsUrl($bandSpace->id, $rider->id) . '/' . $section->id,
            ['title' => 'Sonorisation et retours'],
            self::PATCH_HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderSection',
            '@id' => $this->sectionsUrl($bandSpace->id, $rider->id) . '/' . $section->id,
            '@type' => 'TechRiderSection',
            'id' => (string) $section->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'title' => 'Sonorisation et retours',
            'content' => self::SAMPLE_CONTENT,
            'position' => 0,
            'creation_datetime' => $section->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => $section->updateDatetime?->format(DateTimeInterface::ATOM),
        ]);
    }

    public function test_patch_content_to_null_clears_it(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $section = TechRiderSectionFactory::new([
            'techRider' => $rider,
            'title' => 'Divers',
            'content' => self::SAMPLE_CONTENT,
            'position' => 0,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->sectionsUrl($bandSpace->id, $rider->id) . '/' . $section->id,
            ['content' => null],
            self::PATCH_HEADERS,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderSection',
            '@id' => $this->sectionsUrl($bandSpace->id, $rider->id) . '/' . $section->id,
            '@type' => 'TechRiderSection',
            'id' => (string) $section->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'title' => 'Divers',
            'content' => null,
            'position' => 0,
            'creation_datetime' => $section->creationDatetime->format(DateTimeInterface::ATOM),
            'update_datetime' => $section->updateDatetime?->format(DateTimeInterface::ATOM),
        ]);
    }

    public function test_delete_section(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $section = TechRiderSectionFactory::new(['techRider' => $rider, 'title' => 'Catering', 'position' => 0])->create();
        $sectionId = (string) $section->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', $this->sectionsUrl($bandSpace->id, $rider->id) . '/' . $sectionId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertCount(0, self::getContainer()->get(TechRiderSectionRepository::class)->findByRider($rider));

        $activities = self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $sectionId);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_section_removed', $activities[0]->type);
        $this->assertSame(['rider_name' => 'Rider', 'title' => 'Catering'], $activities[0]->payload);
    }

    public function test_patch_section_from_another_rider_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $mine = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Mine'])->create();
        $other = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Other'])->create();
        $theirSection = TechRiderSectionFactory::new(['techRider' => $other, 'title' => 'Ailleurs'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            $this->sectionsUrl($bandSpace->id, $mine->id) . '/' . $theirSection->id,
            ['title' => 'Détourné'],
            self::PATCH_HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Section introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Section introuvable',
        ]);
    }

    public function test_delete_section_from_another_rider_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $mine = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $other = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();
        $theirSection = TechRiderSectionFactory::new(['techRider' => $other])->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', $this->sectionsUrl($bandSpace->id, $mine->id) . '/' . $theirSection->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Section introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Section introuvable',
        ]);
    }

    public function test_create_section_not_member(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($outsider);
        $this->client->jsonRequest(
            'POST',
            $this->sectionsUrl($bandSpace->id, $rider->id),
            ['title' => 'Rejetée'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }

    public function test_create_section_blocked_when_space_pending_deletion(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            $this->sectionsUrl($bandSpace->id, $rider->id),
            ['title' => 'Bloquée'],
            self::HEADERS,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
        ]);
    }

    /**
     * Sections are embedded on the rider, and the section list is where the tab reads from,
     * so the ordering has to survive a round trip through the API rather than only in SQL.
     */
    public function test_sections_come_back_inline_on_the_rider_in_position_order(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Rider',
            'creationDatetime' => new DateTime('2026-01-15T10:00:00+00:00'),
        ])->create();

        // Created out of order on purpose: position, not insertion order, decides.
        TechRiderSectionFactory::new(['techRider' => $rider, 'title' => 'Troisième', 'position' => 2])->create();
        TechRiderSectionFactory::new(['techRider' => $rider, 'title' => 'Première', 'position' => 0])->create();
        TechRiderSectionFactory::new(['techRider' => $rider, 'title' => 'Deuxième', 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id);

        $this->assertResponseIsSuccessful();
        $body = $this->getResponseAsArray();

        $this->assertSame(
            ['Première', 'Deuxième', 'Troisième'],
            array_column($body['sections'], 'title'),
        );
        $this->assertSame(3, $body['section_count']);
    }

    /**
     * The collection is the rider switcher's payload. It must never carry section content:
     * riders gain patch rows and a stage plot next, and a space with several of them would
     * otherwise ship its whole rider corpus to render a dropdown of names.
     */
    public function test_collection_reports_section_count_and_never_section_content(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Rider',
            'creationDatetime' => new DateTime('2026-01-15T10:00:00+00:00'),
        ])->create();
        TechRiderSectionFactory::new([
            'techRider' => $rider,
            'title' => 'Sonorisation',
            'content' => self::SAMPLE_CONTENT,
            'position' => 0,
        ])->create();
        TechRiderSectionFactory::new(['techRider' => $rider, 'title' => 'Catering', 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/tech_riders');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRider',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
                    '@type' => 'TechRider',
                    'id' => (string) $rider->id,
                    'band_space_id' => (string) $bandSpace->id,
                    'name' => 'Rider',
                    'created_by_username' => null,
                    'archive_datetime' => null,
                    'creation_datetime' => $rider->creationDatetime->format(DateTimeInterface::ATOM),
                    'update_datetime' => null,
                    'section_count' => 2,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    private function sectionsUrl(string $bandSpaceId, string $riderId): string
    {
        return '/api/band_spaces/' . $bandSpaceId . '/tech_riders/' . $riderId . '/sections';
    }
}
