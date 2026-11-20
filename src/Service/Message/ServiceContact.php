<?php declare(strict_types=1);

namespace App\Service\Message;

use App\Entity\Service\Service;

/** A message sent from a service page (#1408): « Poser une question », or « Demander un devis » with its answers. */
final readonly class ServiceContact
{
    /** @var array<string, int|string>|null */
    public ?array $quote;

    /** @param array<string, mixed>|null $quote answers already checked against the kind's quoteQuestions() */
    public function __construct(
        public Service $service,
        ?array $quote = null,
    ) {
        $this->quote = $quote !== null ? self::answersGiven($service, $quote) : null;
    }

    /**
     * In asking order, so the same request sent twice compares equal (MessageSenderProcedure::isLatestOrigin()).
     *
     * @param array<string, mixed> $quote
     *
     * @return array<string, int|string>
     */
    private static function answersGiven(Service $service, array $quote): array
    {
        $answers = [];
        foreach (array_keys($service->kind->quoteQuestions()->fields ?? []) as $question) {
            $answer = $quote[$question] ?? null;
            if ($answer !== null && $answer !== '') {
                $answers[$question] = $answer;
            }
        }

        return $answers;
    }
}
