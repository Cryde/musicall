<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Builder\Moderation;

use App\Entity\Moderation\ModerationAction;
use App\Entity\Report\Report;
use App\Enum\Moderation\ModerationActionType;
use App\Enum\Moderation\ModerationBasis;
use App\Enum\Report\ReportTargetType;
use App\Service\Builder\Moderation\StatementOfReasonsBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

class StatementOfReasonsBuilderTest extends TestCase
{
    /** A private conversation has no label (#1125): the statement names its type only. */
    public function test_a_reported_private_message_is_named_by_its_type_only(): void
    {
        $report = new Report();
        $report->targetType = ReportTargetType::Message;
        $action = $this->suspension(ModerationBasis::Harassment);
        $action->report = $report;

        $statement = $this->builder()->build($action, 'fr');

        self::assertSame('Un message privé', $statement->reportedContent);
    }

    /** A missing translation would email the raw key to the member. */
    #[DataProvider('everyBasisInEveryLocale')]
    public function test_every_basis_reads_as_a_sentence(ModerationBasis $basis, string $locale): void
    {
        $statement = $this->builder()->build($this->suspension($basis), $locale);

        self::assertStringNotContainsString('basis.', $statement->basis);
        self::assertNotSame('', $statement->basis);
    }

    /** @return iterable<string, array{ModerationBasis, string}> */
    public static function everyBasisInEveryLocale(): iterable
    {
        foreach (ModerationBasis::cases() as $basis) {
            foreach (['fr', 'en'] as $locale) {
                yield $basis->value . ' ' . $locale => [$basis, $locale];
            }
        }
    }

    #[DataProvider('everyReportedTypeInEveryLocale')]
    public function test_every_reported_type_reads_as_a_sentence(ReportTargetType $type, string $locale): void
    {
        $report = new Report();
        $report->targetType = $type;
        $action = $this->suspension(ModerationBasis::Spam);
        $action->report = $report;

        $statement = $this->builder()->build($action, $locale);

        self::assertStringNotContainsString('target_type.', $statement->reportedContent);
        self::assertNotSame('', $statement->reportedContent);
    }

    /** @return iterable<string, array{ReportTargetType, string}> */
    public static function everyReportedTypeInEveryLocale(): iterable
    {
        foreach (ReportTargetType::cases() as $type) {
            foreach (['fr', 'en'] as $locale) {
                yield $type->value . ' ' . $locale => [$type, $locale];
            }
        }
    }

    public function test_a_locale_without_a_catalogue_reads_french(): void
    {
        $statement = $this->builder()->build($this->suspension(ModerationBasis::IllegalContent), 'de');

        self::assertSame('Contenu illicite au regard de la loi applicable', $statement->basis);
    }

    public function test_a_decision_without_a_basis_has_no_statement(): void
    {
        $this->expectException(\LogicException::class);

        $this->builder()->build($this->suspension(null), 'fr');
    }

    public function test_a_lift_is_no_decision_against_anyone(): void
    {
        $action = $this->suspension(ModerationBasis::Spam);
        $action->type = ModerationActionType::SuspensionLifted;

        $this->expectException(\LogicException::class);

        $this->builder()->build($action, 'fr');
    }

    private function suspension(?ModerationBasis $basis): ModerationAction
    {
        $action = new ModerationAction();
        $action->type = ModerationActionType::AccountSuspended;
        $action->reason = 'Spam répété';
        $action->basis = $basis;

        return $action;
    }

    private function builder(): StatementOfReasonsBuilder
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['fr']);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (['fr', 'en'] as $locale) {
            $translator->addResource('yaml', sprintf('%s/../../../../../translations/moderation+intl-icu.%s.yaml', __DIR__, $locale), $locale, 'moderation+intl-icu');
        }

        return new StatementOfReasonsBuilder($translator);
    }
}
