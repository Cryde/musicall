<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceRecurrenceCreate;
use App\Enum\BandSpace\RecurrenceInterval;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RecurrenceNoOverlapValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_14dc205d-a2de-42f0-bcba-aa085fd017c2';

    public function __construct(
        private readonly FinanceRecurrenceRepository $recurrenceRepository,
        private readonly FinanceCategoryRepository $categoryRepository,
        private readonly BandSpaceRepository $bandSpaceRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RecurrenceNoOverlap) {
            throw new UnexpectedTypeException($constraint, RecurrenceNoOverlap::class);
        }

        if (!$value instanceof FinanceRecurrenceCreate) {
            return;
        }

        if (!isset($value->categoryId, $value->interval, $value->startDate, $value->endDate)) {
            return;
        }

        if (!uuid_is_valid($value->categoryId)) {
            return;
        }

        $bandSpaceId = $this->requestStack->getCurrentRequest()?->attributes->get('_route_params')['bandSpaceId'] ?? null;
        if ($bandSpaceId === null) {
            return;
        }

        $bandSpace = $this->bandSpaceRepository->find($bandSpaceId);
        if ($bandSpace === null) {
            return;
        }

        $category = $this->categoryRepository->findOneByIdAndBandSpace($value->categoryId, $bandSpace);
        if (!$category instanceof \App\Entity\BandSpace\FinanceCategory) {
            return;
        }

        $interval = RecurrenceInterval::tryFrom($value->interval);
        if ($interval === null) {
            return;
        }

        $startDate = new \DateTimeImmutable($value->startDate);
        $endDate = new \DateTimeImmutable($value->endDate);

        if ($this->recurrenceRepository->hasOverlap($category, $interval, $startDate, $endDate)) {
            $this->context->buildViolation($constraint->message)
                ->atPath('interval')
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
