<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceCategoryCreate;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Repository\BandSpace\FinanceCategoryRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CategoryMaxDepthValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_f1a2b3c4-d5e6-7890-abcd-ef1234567890';

    public function __construct(
        private readonly FinanceCategoryRepository $categoryRepository,
        private readonly BandSpaceRepository $bandSpaceRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CategoryMaxDepth) {
            throw new UnexpectedTypeException($constraint, CategoryMaxDepth::class);
        }

        if (!$value instanceof FinanceCategoryCreate) {
            return;
        }

        if ($value->parentId === null) {
            return;
        }

        if (!uuid_is_valid($value->parentId)) {
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

        $parent = $this->categoryRepository->findOneByIdAndBandSpace($value->parentId, $bandSpace);
        if (!$parent instanceof \App\Entity\BandSpace\FinanceCategory) {
            return;
        }

        $depth = 1;
        $ancestor = $parent;
        while ($ancestor->parent instanceof \App\Entity\BandSpace\FinanceCategory) {
            $depth++;
            $ancestor = $ancestor->parent;
        }

        if ($depth >= CategoryMaxDepth::MAX_DEPTH) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ limit }}', (string) CategoryMaxDepth::MAX_DEPTH)
                ->atPath('parentId')
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
