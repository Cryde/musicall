<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceEntrySplitCreate;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SplitNotPersonalValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_f9cf28b5-5477-4fb0-81d7-b5c1547c56ac';

    public function __construct(
        private readonly FinanceEntryRepository $entryRepository,
        private readonly BandSpaceRepository $bandSpaceRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SplitNotPersonal) {
            throw new UnexpectedTypeException($constraint, SplitNotPersonal::class);
        }

        if (!$value instanceof FinanceEntrySplitCreate) {
            return;
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
        if ($entry === null) {
            return;
        }

        if ($entry->scope === FinanceEntryScope::Personal) {
            $this->context->buildViolation($constraint->message)
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
