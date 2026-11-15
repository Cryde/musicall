<?php declare(strict_types=1);

namespace App\Service\Builder\Moderation;

/** What DSA art. 17 asks a moderation decision to tell the person it affects, already in their language. */
final readonly class StatementOfReasons
{
    public function __construct(
        public string $decision,
        public string $decisionDate,
        /** The moderator's own words. */
        public string $facts,
        /** Empty when the decision did not come from a report. */
        public string $reportedContent,
        public string $basis,
        public string $humanDecision,
        public string $redress,
    ) {
    }
}
