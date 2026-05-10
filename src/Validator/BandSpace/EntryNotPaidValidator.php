<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\Enum\BandSpace\FinanceEntryStatus;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EntryNotPaidValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_a1d2e3f4-5678-49ab-bcde-f01234567890';

    public function __construct(
        private readonly FinanceEntryRepository $entryRepository,
        private readonly BandSpaceRepository $bandSpaceRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EntryNotPaid) {
            throw new UnexpectedTypeException($constraint, EntryNotPaid::class);
        }

        $routeParams = $this->requestStack->getCurrentRequest()?->attributes->get('_route_params') ?? [];
        $bandSpaceId = $routeParams['bandSpaceId'] ?? null;
        $entryId = $routeParams['entryId'] ?? null;

        if ($bandSpaceId === null || $entryId === null) {
            return;
        }

        $bandSpace = $this->bandSpaceRepository->find($bandSpaceId);
        if ($bandSpace === null) {
            return;
        }

        $entry = $this->entryRepository->findOneByIdAndBandSpace($entryId, $bandSpace);
        if (!$entry instanceof \App\Entity\BandSpace\FinanceEntry) {
            return;
        }

        if ($entry->status === FinanceEntryStatus::Paid) {
            $this->context->buildViolation($constraint->message)
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
