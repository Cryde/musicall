<?php

declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\BandSpaceNoteCreate;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NoteMaxDepthValidator extends ConstraintValidator
{
    public const string ERROR_CODE = 'music_all_a3b2c1d0-4e5f-6a7b-8c9d-0e1f2a3b4c5d';

    public function __construct(
        private readonly BandSpaceNoteRepository $noteRepository,
        private readonly BandSpaceRepository $bandSpaceRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoteMaxDepth) {
            throw new UnexpectedTypeException($constraint, NoteMaxDepth::class);
        }

        if (!$value instanceof BandSpaceNoteCreate) {
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

        $parent = $this->noteRepository->findOneByIdAndBandSpace($value->parentId, $bandSpace);
        if (!$parent instanceof \App\Entity\BandSpace\BandSpaceNote) {
            return;
        }

        $depth = 1;
        $ancestor = $parent;
        while ($ancestor->parent instanceof \App\Entity\BandSpace\BandSpaceNote) {
            $depth++;
            $ancestor = $ancestor->parent;
        }

        if ($depth >= NoteMaxDepth::MAX_DEPTH) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ limit }}', (string) NoteMaxDepth::MAX_DEPTH)
                ->atPath('parentId')
                ->setCode(self::ERROR_CODE)
                ->addViolation();
        }
    }
}
