<?php declare(strict_types=1);

namespace App\Service\Builder\Moderation;

use App\Entity\Moderation\ModerationAction;
use App\Entity\Report\Report;
use App\Enum\Moderation\ModerationActionType;
use App\Enum\Moderation\ModerationBasis;
use App\Enum\Report\ReportTargetType;
use App\Service\Mail\Brevo\BrevoDate;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The statement of reasons of a moderation decision (DSA art. 17, terms §7.3, #1126). The decision is
 * read off the action's type, so a content removal (#1122) only adds its own case.
 */
readonly class StatementOfReasonsBuilder
{
    private const string DOMAIN = 'moderation';

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /** @param string $locale the recipient's, never the moderator's */
    public function build(ModerationAction $action, string $locale): StatementOfReasons
    {
        if (!$action->basis instanceof ModerationBasis) {
            throw new \LogicException('A statement of reasons names the basis of the decision');
        }
        $decisionKey = match ($action->type) {
            ModerationActionType::AccountSuspended => 'statement.decision.account_suspended',
            ModerationActionType::ReportDismissed, ModerationActionType::SuspensionLifted => throw new \LogicException(
                sprintf('A "%s" action is not a decision against anyone', $action->type->value),
            ),
        };

        return new StatementOfReasons(
            decision: $this->trans($decisionKey, $locale),
            decisionDate: BrevoDate::format($action->creationDatetime, $locale),
            facts: (string) $action->reason,
            reportedContent: $this->reportedContent($action->report, $locale),
            basis: $this->trans(self::basisKey($action->basis), $locale),
            humanDecision: $this->trans('statement.human_decision', $locale),
            redress: $this->trans('statement.redress', $locale),
        );
    }

    /** The type of what was reported and the label its reporter was told (#1125), never who reported it. */
    private function reportedContent(?Report $report, string $locale): string
    {
        if (!$report instanceof Report) {
            return '';
        }
        $type = $this->trans(self::targetTypeKey($report->targetType), $locale);
        $label = $report->targetLabel();

        return $label === null ? $type : $this->trans('statement.reported_content', $locale, ['type' => $type, 'label' => $label]);
    }

    // Each key spelled out, so TranslationCatalogueTest can match it with the catalogue.
    private static function basisKey(ModerationBasis $basis): string
    {
        return match ($basis) {
            ModerationBasis::IllegalContent => 'basis.illegal_content',
            ModerationBasis::ObjectionableContent => 'basis.objectionable_content',
            ModerationBasis::Harassment => 'basis.harassment',
            ModerationBasis::Impersonation => 'basis.impersonation',
            ModerationBasis::Spam => 'basis.spam',
            ModerationBasis::Security => 'basis.security',
            ModerationBasis::PersonalData => 'basis.personal_data',
            ModerationBasis::IntellectualProperty => 'basis.intellectual_property',
            ModerationBasis::BandSpaceStorage => 'basis.band_space_storage',
            ModerationBasis::QuotaCircumvention => 'basis.quota_circumvention',
        };
    }

    private static function targetTypeKey(ReportTargetType $type): string
    {
        return match ($type) {
            ReportTargetType::User => 'target_type.user',
            ReportTargetType::Announce => 'target_type.announce',
            ReportTargetType::Message => 'target_type.message',
            ReportTargetType::BandChatMessage => 'target_type.band_chat_message',
            ReportTargetType::ProfileMedia => 'target_type.profile_media',
            ReportTargetType::ForumPost => 'target_type.forum_post',
            ReportTargetType::Comment => 'target_type.comment',
            ReportTargetType::Publication => 'target_type.publication',
            ReportTargetType::ProfilePost => 'target_type.profile_post',
            ReportTargetType::ProfilePostComment => 'target_type.profile_post_comment',
        };
    }

    /** @param array<string, string> $parameters */
    private function trans(string $key, string $locale, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, self::DOMAIN, $locale);
    }
}
