<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Block;

use App\Entity\Attribute\Instrument;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\Teacher\TeacherProfileInstrumentFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Two users with a block between them stop finding each other, whoever placed it (#1117). Musician
 * search and user search are covered next to their own tests.
 */
#[ResetDatabase]
class BlockedUserListingTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_latest_announces_leave_out_users_blocked_either_way(): void
    {
        [$viewer, $blockedByViewer, $blockingViewer] = $this->blockedBothWays();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'visible', 'email' => 'visible@example.com']);
        $visible = $this->announce($author, $drum);
        $this->announce($blockedByViewer, $drum);
        $this->announce($blockingViewer, $drum);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/musician_announces/last');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->latestAnnouncesBody([$this->announceMember($visible, $author, $drum)]));
    }

    public function test_a_visitor_still_sees_every_latest_announce(): void
    {
        [, $blockedByViewer, $blockingViewer] = $this->blockedBothWays();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $first = $this->announce($blockedByViewer, $drum, '2026-09-01T10:00:00+00:00');
        $second = $this->announce($blockingViewer, $drum, '2026-09-02T10:00:00+00:00');

        $this->client->request('GET', '/api/musician_announces/last');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->latestAnnouncesBody([
            $this->announceMember($second, $blockingViewer, $drum, '2026-09-02T10:00:00+00:00'),
            $this->announceMember($first, $blockedByViewer, $drum, '2026-09-01T10:00:00+00:00'),
        ]));
    }

    public function test_featured_teachers_leave_out_users_blocked_either_way(): void
    {
        [$viewer, $blockedByViewer, $blockingViewer] = $this->blockedBothWays();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $visible = UserFactory::new()->create(['username' => 'teacher_visible', 'email' => 'teacher.visible@example.com']);
        foreach ([$visible, $blockedByViewer, $blockingViewer] as $teacher) {
            $profile = TeacherProfileFactory::new()->create(['user' => $teacher, 'offersTrial' => false]);
            TeacherProfileInstrumentFactory::new()->create(['teacherProfile' => $profile, 'instrument' => $guitar]);
        }

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/teachers/featured');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FeaturedTeacher',
            '@id' => '/api/teachers/featured',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/featured_teachers/teacher_visible',
                    '@type' => 'FeaturedTeacher',
                    'username' => 'teacher_visible',
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

    /** @return array{User, User, User} the viewer, someone they blocked, and someone who blocked them */
    private function blockedBothWays(): array
    {
        $viewer = UserFactory::new()->asBaseUser()->create(['username' => 'viewer', 'email' => 'viewer@example.com']);
        $blockedByViewer = UserFactory::new()->asBaseUser()->create(['username' => 'blocked_by_viewer', 'email' => 'blocked@example.com']);
        $blockingViewer = UserFactory::new()->asBaseUser()->create(['username' => 'blocking_viewer', 'email' => 'blocking@example.com']);
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blockedByViewer]);
        UserBlockFactory::new()->create(['blocker' => $blockingViewer, 'blocked' => $viewer]);

        return [$viewer, $blockedByViewer, $blockingViewer];
    }

    private function announce(User $author, Instrument $instrument, string $creation = '2026-09-01T10:00:00+00:00'): MusicianAnnounce
    {
        return MusicianAnnounceFactory::new()->create([
            'author' => $author,
            'instrument' => $instrument,
            'styles' => [],
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
            'locationName' => 'Mons',
            'note' => 'Annonce de ' . $author->username,
            'creationDatetime' => new \DateTime($creation),
        ]);
    }

    /** @return array<string, mixed> */
    private function announceMember(MusicianAnnounce $announce, User $author, Instrument $instrument, string $creation = '2026-09-01T10:00:00+00:00'): array
    {
        return [
            '@id' => '/api/musician_announces/' . $announce->id,
            '@type' => 'MusicianAnnounce',
            'id' => $announce->id,
            'creation_datetime' => $creation,
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
            'instrument' => [
                '@type' => 'Instrument',
                'id' => $instrument->id,
                'musician_name' => 'Batteur',
            ],
            'styles' => [],
            'location_name' => 'Mons',
            'note' => 'Annonce de ' . $author->username,
            'author' => [
                '@type' => 'Author',
                'id' => $author->id,
                'username' => $author->username,
                'display_name' => $author->username,
                'has_musician_profile' => false,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $members
     *
     * @return array<string, mixed>
     */
    private function latestAnnouncesBody(array $members): array
    {
        return [
            '@context' => '/api/contexts/MusicianAnnounce',
            '@id' => '/api/musician_announces/last',
            '@type' => 'Collection',
            'member' => $members,
            'totalItems' => count($members),
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/musician_announces/last{?type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'type',
                        'property' => 'type',
                        'required' => false,
                    ],
                ],
            ],
        ];
    }
}
