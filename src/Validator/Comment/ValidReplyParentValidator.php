<?php

declare(strict_types=1);

namespace App\Validator\Comment;

use App\ApiResource\Comment\CommentCreation;
use App\Entity\Comment\Comment;
use App\Repository\Comment\CommentRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidReplyParentValidator extends ConstraintValidator
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidReplyParent) {
            throw new UnexpectedTypeException($constraint, ValidReplyParent::class);
        }

        if ($value === null) {
            return;
        }

        if (!$value instanceof CommentCreation) {
            throw new UnexpectedValueException($value, CommentCreation::class);
        }

        if ($value->parentId === null) {
            return;
        }

        $parent = $this->commentRepository->find($value->parentId);
        if (!$parent instanceof Comment) {
            $this->context->buildViolation($constraint->notFoundMessage)
                ->atPath('parentId')
                ->setCode(ValidReplyParent::NOT_FOUND_CODE)
                ->addViolation();

            return;
        }

        if ($parent->thread->id !== $value->thread->id) {
            $this->context->buildViolation($constraint->wrongThreadMessage)
                ->atPath('parentId')
                ->setCode(ValidReplyParent::WRONG_THREAD_CODE)
                ->addViolation();

            return;
        }

        if ($parent->parent instanceof Comment) {
            $this->context->buildViolation($constraint->alreadyNestedMessage)
                ->atPath('parentId')
                ->setCode(ValidReplyParent::ALREADY_NESTED_CODE)
                ->addViolation();
        }
    }
}
