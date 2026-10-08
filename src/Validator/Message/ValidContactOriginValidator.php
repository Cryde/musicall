<?php declare(strict_types=1);

namespace App\Validator\Message;

use App\ApiResource\Message\MessageUser;
use App\Service\Message\ContactOriginResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidContactOriginValidator extends ConstraintValidator
{
    public const string ERROR_CODE_BOTH = 'music_all_6f0d2b8e-3c41-4a77-9e52-1b8d7c0a4f13';
    public const string ERROR_CODE_ANNOUNCE = 'music_all_a2c94e17-5b6d-4f08-8d3a-9e71f2c5b604';
    public const string ERROR_CODE_TEACHER_PROFILE = 'music_all_d81f3a65-0e2c-47b9-b6a4-5c9e0f27d318';

    public function __construct(
        private readonly ContactOriginResolver $contactOriginResolver,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidContactOrigin) {
            throw new UnexpectedTypeException($constraint, ValidContactOrigin::class);
        }
        if (!$value instanceof MessageUser) {
            throw new UnexpectedValueException($value, MessageUser::class);
        }
        // No recipient is NotBlank's violation to report, not this one.
        if (!isset($value->recipient)) {
            return;
        }

        if ($value->musicianAnnounceId !== null && $value->fromTeacherProfile) {
            $this->context->buildViolation($constraint->bothMessage)->atPath('fromTeacherProfile')->setCode(self::ERROR_CODE_BOTH)->addViolation();

            return;
        }

        if ($value->musicianAnnounceId !== null && $this->contactOriginResolver->musicianAnnounceOf($value->musicianAnnounceId, $value->recipient) === null) {
            $this->context->buildViolation($constraint->announceMessage)->atPath('musicianAnnounceId')->setCode(self::ERROR_CODE_ANNOUNCE)->addViolation();
        }

        if ($value->fromTeacherProfile && $this->contactOriginResolver->teacherProfileOf($value->recipient) === null) {
            $this->context->buildViolation($constraint->teacherProfileMessage)->atPath('fromTeacherProfile')->setCode(self::ERROR_CODE_TEACHER_PROFILE)->addViolation();
        }
    }
}
