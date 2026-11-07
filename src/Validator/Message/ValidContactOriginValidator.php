<?php declare(strict_types=1);

namespace App\Validator\Message;

use App\ApiResource\Message\MessageUser;
use App\Entity\Teacher\TeacherProfile;
use App\Enum\Message\LessonRequest;
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
    public const string ERROR_CODE_LESSON_WITHOUT_TEACHER_PROFILE = 'music_all_4b7e9c21-8d3f-4a65-b0e2-6f1c9a8d5e37';
    public const string ERROR_CODE_TEACHER_INSTRUMENT = 'music_all_c3a8f610-2e4b-4d97-9f15-7b0e3d6a2c84';
    public const string ERROR_CODE_LESSON_REQUEST = 'music_all_e95d2b74-6a1c-4f38-8e07-2d4b9c5f1a63';

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

        if (!$value->fromTeacherProfile) {
            $this->refuseLessonFields($value, $constraint);

            return;
        }

        $profile = $this->contactOriginResolver->teacherProfileOf($value->recipient);
        if (!$profile instanceof TeacherProfile) {
            $this->context->buildViolation($constraint->teacherProfileMessage)->atPath('fromTeacherProfile')->setCode(self::ERROR_CODE_TEACHER_PROFILE)->addViolation();

            return;
        }

        if ($value->teacherInstrumentId !== null && $this->contactOriginResolver->taughtInstrumentOf($profile, $value->teacherInstrumentId) === null) {
            $this->context->buildViolation($constraint->teacherInstrumentMessage)->atPath('teacherInstrumentId')->setCode(self::ERROR_CODE_TEACHER_INSTRUMENT)->addViolation();
        }
        // Not checked against the teacher's offersTrial: it may change between the page load and the send.
        if ($value->lessonRequest !== null && LessonRequest::tryFrom($value->lessonRequest) === null) {
            $this->context->buildViolation($constraint->lessonRequestMessage)->atPath('lessonRequest')->setCode(self::ERROR_CODE_LESSON_REQUEST)->addViolation();
        }
    }

    /** A lesson request only comes from a teacher profile (#1322). */
    private function refuseLessonFields(MessageUser $value, ValidContactOrigin $constraint): void
    {
        foreach (['teacherInstrumentId' => $value->teacherInstrumentId, 'lessonRequest' => $value->lessonRequest] as $path => $field) {
            if ($field !== null) {
                $this->context->buildViolation($constraint->lessonWithoutTeacherProfileMessage)->atPath($path)->setCode(self::ERROR_CODE_LESSON_WITHOUT_TEACHER_PROFILE)->addViolation();
            }
        }
    }
}
