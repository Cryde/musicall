<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\BandSpace\BandSpaceNote;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceActivityFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use Ramsey\Uuid\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The note editor autosaves every two seconds, so recording every body change turned one writing
 * session into dozens of near identical feed rows. The dashboard widget shows the ten most recent
 * activities of the whole space, so that one session pushed agenda, finance, files and tasks off
 * every member's dashboard. These pin the coalescing that prevents it, and the four things it must
 * not swallow.
 *
 * Each test makes a single API call and seeds any earlier activity directly, because loginUser()
 * only survives one request.
 */
#[ResetDatabase]
class BandSpaceNoteActivityTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array PATCH_HEADERS = [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_a_first_content_save_records_an_activity(): void
    {
        [$user, $bandSpace, $note] = $this->seedNote();

        $this->patchAs($user, $bandSpace, $note, [
            'content' => $this->doc('Premier jet'),
            'expected_content_version' => 1,
        ]);

        $activities = $this->activitiesFor($bandSpace, $note);
        $this->assertCount(1, $activities);
        $this->assertSame('note_content_updated', $activities[0]->type);
        $this->assertNull($activities[0]->payload);
    }

    public function test_a_second_content_save_inside_the_window_records_nothing_new(): void
    {
        [$user, $bandSpace, $note] = $this->seedNote();
        $this->seedContentActivity($bandSpace, $note, $user, new DateTime('-2 minutes'));

        $this->patchAs($user, $bandSpace, $note, [
            'content' => $this->doc('Deuxième jet'),
            'expected_content_version' => 1,
        ]);

        $this->assertCount(1, $this->activitiesFor($bandSpace, $note));
    }

    /**
     * Past the window the trail resumes, otherwise a note revisited every week would show a single
     * entry forever.
     */
    public function test_a_content_save_after_the_window_records_a_new_activity(): void
    {
        [$user, $bandSpace, $note] = $this->seedNote();
        $this->seedContentActivity($bandSpace, $note, $user, new DateTime('-1 hour'));

        $this->patchAs($user, $bandSpace, $note, [
            'content' => $this->doc('Repris plus tard'),
            'expected_content_version' => 1,
        ]);

        $this->assertCount(2, $this->activitiesFor($bandSpace, $note));
    }

    /**
     * Coalescing is per member as well as per note: two people writing in the same note inside the
     * same window are two facts, and collapsing them would hide who did what.
     */
    public function test_another_member_editing_inside_the_window_records_their_own_activity(): void
    {
        [$user, $bandSpace, $note] = $this->seedNote();

        $other = UserFactory::new()->create(['username' => 'second_member', 'email' => 'second@test.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();
        $this->seedContentActivity($bandSpace, $note, $other, new DateTime('-2 minutes'));

        $this->patchAs($user, $bandSpace, $note, [
            'content' => $this->doc('Par quelqu\'un d\'autre'),
            'expected_content_version' => 1,
        ]);

        $this->assertCount(2, $this->activitiesFor($bandSpace, $note));
    }

    /**
     * Coalescing hides feed rows, never revisions: the next autosave of everyone else editing this
     * note still has to be refused.
     */
    public function test_a_coalesced_content_save_still_bumps_the_revision(): void
    {
        [$user, $bandSpace, $note] = $this->seedNote();
        $this->seedContentActivity($bandSpace, $note, $user, new DateTime('-2 minutes'));

        $this->patchAs($user, $bandSpace, $note, [
            'content' => $this->doc('Deuxième jet'),
            'expected_content_version' => 1,
        ]);

        $refreshed = self::getContainer()->get(BandSpaceNoteRepository::class)->find($note->id);
        $this->assertSame(2, $refreshed->contentVersion);
        $this->assertCount(1, $this->activitiesFor($bandSpace, $note));
    }

    /** A rename is a deliberate act, not an autosave, so coalescing must never swallow it. */
    public function test_a_rename_is_recorded_even_when_the_content_change_is_coalesced(): void
    {
        [$user, $bandSpace, $note] = $this->seedNote();
        $this->seedContentActivity($bandSpace, $note, $user, new DateTime('-2 minutes'));

        $this->patchAs($user, $bandSpace, $note, [
            'title' => 'Répétition du 12',
            'content' => $this->doc('Contenu revu'),
            'expected_content_version' => 1,
        ]);

        $this->assertSame(
            ['note_content_updated', 'note_renamed'],
            $this->activityTypesFor($bandSpace, $note),
        );
    }

    /** Same for the emoji picker: one pick, one entry, whatever the body is doing. */
    public function test_an_emoji_change_is_recorded_even_when_the_content_change_is_coalesced(): void
    {
        [$user, $bandSpace, $note] = $this->seedNote();
        $this->seedContentActivity($bandSpace, $note, $user, new DateTime('-2 minutes'));

        $this->patchAs($user, $bandSpace, $note, [
            'emoji' => '🎵',
            'content' => $this->doc('Contenu revu'),
            'expected_content_version' => 1,
        ]);

        $this->assertSame(
            ['note_content_updated', 'note_emoji_changed'],
            $this->activityTypesFor($bandSpace, $note),
        );
    }

    /**
     * @return array{0: User, 1: BandSpace, 2: BandSpaceNote}
     */
    private function seedNote(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Répétition',
            'position' => 0,
            'creationDatetime' => new DateTime('2024-01-01 10:00:00'),
        ])->create();

        return [$user, $bandSpace, $note];
    }

    private function seedContentActivity(
        BandSpace $bandSpace,
        BandSpaceNote $note,
        User $actor,
        DateTime $when,
    ): void {
        BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Notes,
            'type' => 'note_content_updated',
            'resourceId' => Uuid::fromString((string) $note->id),
            'actor' => $actor,
            'creationDatetime' => $when,
        ])->create();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patchAs(User $user, BandSpace $bandSpace, BandSpaceNote $note, array $payload): void
    {
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id,
            $payload,
            self::PATCH_HEADERS,
        );
        $this->assertResponseIsSuccessful();
    }

    /**
     * @return array<int, BandSpaceActivity>
     */
    private function activitiesFor(BandSpace $bandSpace, BandSpaceNote $note): array
    {
        return self::getContainer()->get(BandSpaceActivityRepository::class)
            ->findForResource($bandSpace, BandSpaceModule::Notes, $note->id);
    }

    /**
     * Sorted, because two activities recorded in the same request share a timestamp and the feed
     * order between them is not decided by anything.
     *
     * @return array<int, string>
     */
    private function activityTypesFor(BandSpace $bandSpace, BandSpaceNote $note): array
    {
        $types = array_map(
            static fn (BandSpaceActivity $activity): string => $activity->type,
            $this->activitiesFor($bandSpace, $note),
        );
        sort($types);

        return $types;
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
