<?php declare(strict_types=1);

namespace App\Tests\Integration\Service\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Enum\BandSpace\MembershipStatus;
use App\Service\BandSpace\TechRider\TechRiderContactsRenderer;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The line formatting is the part a venue reads, and it is shared with whatever renders the export,
 * so it gets tested on its own rather than only through the API. A change here that nobody notices
 * is a change to a document sent to strangers.
 */
#[ResetDatabase]
class TechRiderContactsRendererTest extends KernelTestCase
{
    public function test_a_member_with_instruments_prints_name_then_instruments(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $this->member($bandSpace, 'kenny', 'Kenny', ['Basse', 'Chœurs']);

        $this->assertSame(['Kenny /// Basse, Chœurs'], $this->render($bandSpace)['lines']);
    }

    /** No instruments means the name alone: a trailing separator would be printed on the page. */
    public function test_a_member_without_instruments_prints_the_name_alone(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $this->member($bandSpace, 'roadie', 'Roadie', []);

        $this->assertSame(['Roadie'], $this->render($bandSpace)['lines']);
    }

    public function test_the_username_stands_in_when_no_stage_name_is_set(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $this->member($bandSpace, 'jeremy_login', null, ['Batterie']);

        $this->assertSame(['jeremy_login /// Batterie'], $this->render($bandSpace)['lines']);
    }

    /**
     * Two renders of the same band produce the same document. The roster query orders by join
     * date, so without an explicit sort the printed page would reshuffle whenever somebody
     * rejoined, and nothing downstream would notice.
     */
    public function test_the_order_is_deterministic_and_by_printed_name(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $this->member($bandSpace, 'zoe', 'Zoé', []);
        $this->member($bandSpace, 'alice', 'Alice', []);
        $this->member($bandSpace, 'bob', null, []);

        $first = $this->render($bandSpace)['lines'];

        $this->assertSame(['Alice', 'Zoé', 'bob'], $first);
        $this->assertSame($first, $this->render($bandSpace)['lines']);
    }

    /** Two members who chose the same stage name are separated by username, never by chance. */
    public function test_a_tie_on_stage_name_is_broken_by_username(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $this->member($bandSpace, 'zzz_user', 'Alex', ['Basse']);
        $this->member($bandSpace, 'aaa_user', 'Alex', ['Batterie']);

        $this->assertSame(['Alex /// Batterie', 'Alex /// Basse'], $this->render($bandSpace)['lines']);
    }

    public function test_a_member_who_left_is_not_rendered(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $this->member($bandSpace, 'active_user', 'Actif', []);
        $this->member($bandSpace, 'gone_user', 'Parti', [], MembershipStatus::Left);

        $this->assertSame(['Actif'], $this->render($bandSpace)['lines']);
    }

    public function test_emails_follow_the_same_order_and_only_when_asked_for(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $this->member($bandSpace, 'zoe', 'Zoé', []);
        $this->member($bandSpace, 'alice', 'Alice', []);

        $this->assertSame([], $this->render($bandSpace)['emails']);
        $this->assertSame(
            ['alice@test.com', 'zoe@test.com'],
            $this->render($bandSpace, showEmails: true)['emails'],
        );
    }

    /**
     * @return array{lines: list<string>, emails: list<string>}
     */
    private function render(BandSpace $bandSpace, bool $showEmails = false): array
    {
        return self::getContainer()->get(TechRiderContactsRenderer::class)->render($bandSpace, $showEmails);
    }

    /**
     * @param list<string> $instrumentNames
     */
    private function member(
        BandSpace $bandSpace,
        string $username,
        ?string $stageName,
        array $instrumentNames,
        MembershipStatus $status = MembershipStatus::Active,
    ): void {
        $instruments = array_map(
            static fn (string $name): object => InstrumentFactory::new()->create([
                'name' => $name,
                'musicianName' => $name . ' player',
                'slug' => mb_strtolower($name) . '-' . $username,
            ]),
            $instrumentNames,
        );

        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => UserFactory::new()->create([
                'username' => $username,
                'email' => $username . '@test.com',
            ]),
            'stageName' => $stageName,
            'status' => $status,
            'leftDatetime' => $status === MembershipStatus::Active ? null : new DateTime('-1 day'),
            'instruments' => new ArrayCollection($instruments),
        ])->create();
    }
}
