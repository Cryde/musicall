<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TechRiderSection;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceActivityFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderSectionFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use Ramsey\Uuid\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The section editor autosaves on a debounce, so recording every content change would turn
 * an afternoon of writing into dozens of near identical feed rows. These pin the coalescing
 * that prevents it, and the three things it must not swallow.
 *
 * Each test makes a single API call and seeds any earlier activity directly, because
 * loginUser() only survives one request.
 */
#[ResetDatabase]
class TechRiderSectionActivityTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array PATCH_HEADERS = [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_a_first_content_save_records_an_activity(): void
    {
        [$user, $bandSpace, $rider, $section] = $this->seedRider();

        $this->patchAs($user, $bandSpace, $rider, $section, ['content' => $this->doc('Premier jet')]);

        $activities = $this->activitiesFor($bandSpace, $section);
        $this->assertCount(1, $activities);
        $this->assertSame('rider_section_updated', $activities[0]->type);
        $this->assertSame(['rider_name' => 'Rider', 'title' => 'Sonorisation'], $activities[0]->payload);
    }

    public function test_a_second_content_save_inside_the_window_records_nothing_new(): void
    {
        [$user, $bandSpace, $rider, $section] = $this->seedRider();
        $this->seedContentActivity($bandSpace, $section, $user, new DateTime('-2 minutes'));

        $this->patchAs($user, $bandSpace, $rider, $section, ['content' => $this->doc('Deuxième jet')]);

        $this->assertCount(1, $this->activitiesFor($bandSpace, $section));
    }

    /**
     * Past the window the trail resumes, otherwise a section revisited every week would show
     * a single entry forever.
     */
    public function test_a_content_save_after_the_window_records_a_new_activity(): void
    {
        [$user, $bandSpace, $rider, $section] = $this->seedRider();
        $this->seedContentActivity($bandSpace, $section, $user, new DateTime('-1 hour'));

        $this->patchAs($user, $bandSpace, $rider, $section, ['content' => $this->doc('Repris plus tard')]);

        $this->assertCount(2, $this->activitiesFor($bandSpace, $section));
    }

    /**
     * Coalescing is per actor: two people writing in the same section inside the same window
     * are two facts, and collapsing them would hide who did what.
     */
    public function test_another_member_editing_inside_the_window_records_their_own_activity(): void
    {
        [$user, $bandSpace, $rider, $section] = $this->seedRider();

        $other = UserFactory::new()->create(['username' => 'second_member', 'email' => 'second@test.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();
        $this->seedContentActivity($bandSpace, $section, $other, new DateTime('-2 minutes'));

        $this->patchAs($user, $bandSpace, $rider, $section, ['content' => $this->doc('Par quelqu\'un d\'autre')]);

        $this->assertCount(2, $this->activitiesFor($bandSpace, $section));
    }

    /**
     * A rename is a deliberate act, not an autosave, so coalescing must never swallow it.
     */
    public function test_a_rename_is_recorded_even_when_the_content_change_is_coalesced(): void
    {
        [$user, $bandSpace, $rider, $section] = $this->seedRider();
        $this->seedContentActivity($bandSpace, $section, $user, new DateTime('-2 minutes'));

        $this->patchAs($user, $bandSpace, $rider, $section, [
            'title' => 'Sonorisation et retours',
            'content' => $this->doc('Contenu revu'),
        ]);

        $types = array_map(
            static fn ($activity): string => $activity->type,
            $this->activitiesFor($bandSpace, $section),
        );
        sort($types);

        $this->assertSame(['rider_section_renamed', 'rider_section_updated'], $types);
    }

    public function test_a_rename_and_a_first_content_save_each_record_their_own_activity(): void
    {
        [$user, $bandSpace, $rider, $section] = $this->seedRider();

        $this->patchAs($user, $bandSpace, $rider, $section, [
            'title' => 'Sonorisation et retours',
            'content' => $this->doc('Contenu'),
        ]);

        $types = array_map(
            static fn ($activity): string => $activity->type,
            $this->activitiesFor($bandSpace, $section),
        );
        sort($types);

        $this->assertSame(['rider_section_renamed', 'rider_section_updated'], $types);
    }

    /**
     * A save that changes nothing is not an edit. Without this the editor's periodic autosave
     * would record activity for a section nobody touched.
     */
    public function test_a_save_that_changes_nothing_records_no_activity(): void
    {
        [$user, $bandSpace, $rider, $section] = $this->seedRider($this->doc('Inchangé'));

        $this->patchAs($user, $bandSpace, $rider, $section, [
            'title' => 'Sonorisation',
            'content' => $this->doc('Inchangé'),
        ]);

        $this->assertCount(0, $this->activitiesFor($bandSpace, $section));
    }

    /**
     * @param array<string, mixed>|null $content
     * @return array{0: User, 1: BandSpace, 2: mixed, 3: TechRiderSection}
     */
    private function seedRider(?array $content = null): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $section = TechRiderSectionFactory::new([
            'techRider' => $rider,
            'title' => 'Sonorisation',
            'content' => $content,
        ])->create();

        return [$user, $bandSpace, $rider, $section];
    }

    private function seedContentActivity(
        BandSpace $bandSpace,
        TechRiderSection $section,
        User $actor,
        DateTime $when,
    ): void {
        BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Rider,
            'type' => 'rider_section_updated',
            'resourceId' => Uuid::fromString((string) $section->id),
            'actor' => $actor,
            'creationDatetime' => $when,
        ])->create();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patchAs(User $user, BandSpace $bandSpace, mixed $rider, TechRiderSection $section, array $payload): void
    {
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/sections/' . $section->id,
            $payload,
            self::PATCH_HEADERS,
        );
        $this->assertResponseIsSuccessful();
    }

    /**
     * @return array<int, \App\Entity\BandSpace\BandSpaceActivity>
     */
    private function activitiesFor(BandSpace $bandSpace, TechRiderSection $section): array
    {
        return self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Rider, $section->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function doc(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            ],
        ];
    }
}
