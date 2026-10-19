<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Entity\User\UserProfile;
use App\Tests\Double\CatalogueTranslator;
use App\Translation\DisplayNameTranslator;
use PHPUnit\Framework\TestCase;

class DisplayNameTranslatorTest extends TestCase
{
    public function test_french_reads_the_label_stored_text_uses(): void
    {
        // Stored text (a task seeded from a message) keeps the constant, so the two must not drift.
        self::assertSame(User::DELETED_DISPLAY_NAME, $this->names('fr')->deletedUser());
    }

    public function test_english_reads_the_english_label(): void
    {
        self::assertSame('Deleted user', $this->names('en')->deletedUser());
    }

    public function test_a_closed_account_is_named_in_the_reader_locale_wherever_it_appears(): void
    {
        $user = self::closedAccount();
        $membership = new BandSpaceMembership();
        $membership->user = $user;
        $membership->stageName = 'Alex';

        self::assertSame(
            ['Deleted user', 'Deleted user'],
            [$this->names('en')->publicName($user), $this->names('en')->memberName($membership)],
        );
    }

    public function test_an_open_account_keeps_its_name(): void
    {
        $user = new User();
        $user->username = 'androidtest_123';
        $user->profile = new UserProfile();
        $membership = new BandSpaceMembership();
        $membership->user = $user;
        $membership->stageName = 'Alex';

        self::assertSame(
            ['androidtest_123', 'Alex'],
            [$this->names('en')->publicName($user), $this->names('en')->memberName($membership)],
        );
    }

    private function names(string $locale): DisplayNameTranslator
    {
        return new DisplayNameTranslator(CatalogueTranslator::in($locale));
    }

    private static function closedAccount(): User
    {
        $user = new User();
        $user->username = 'deleted_c7c9f2e1';
        $user->deletionDatetime = new \DateTimeImmutable('2026-09-01');
        $user->profile = new UserProfile();

        return $user;
    }
}
