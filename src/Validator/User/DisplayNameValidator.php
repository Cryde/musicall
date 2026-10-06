<?php

declare(strict_types=1);

namespace App\Validator\User;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\UserRepository;
use App\Service\User\DisplayNameRules;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class DisplayNameValidator extends ConstraintValidator
{
    public const string FORBIDDEN_CHARACTER_ERROR = 'music_all_6afd2733-41c6-44df-859b-149296c8829c';
    public const string EMOJI_ERROR = 'music_all_8d61813b-22da-41cb-8285-36e00810334c';
    public const string STACKED_MARKS_ERROR = 'music_all_15b26244-bd92-4149-90ba-330db4f3320c';
    public const string NO_LETTER_OR_DIGIT_ERROR = 'music_all_feccafb1-762a-4235-873e-9c6123b52bde';
    public const string LEADING_AT_ERROR = 'music_all_98112b3a-5ed0-49ff-a390-db6c298c4add';
    public const string RESERVED_ERROR = 'music_all_2215d5fd-e133-4a09-8e79-11a748aeda2b';
    public const string USERNAME_TAKEN_ERROR = 'music_all_61e7b798-a5a3-4343-8646-b2d877800aaf';

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly UserRepository $userRepository,
        private readonly BandSpaceMembershipRepository $membershipRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DisplayName) {
            throw new UnexpectedTypeException($constraint, DisplayName::class);
        }
        if ($value === null || $value === '') {
            return;
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        [$owner, $storedName] = $this->ownerAndStoredName($constraint);
        // A PATCH validates the whole resource, so a saved name must not block an unrelated edit,
        // even one that a later username registration has made clash.
        if ($storedName !== null && DisplayNameRules::normalize($storedName) === $value) {
            return;
        }

        [$message, $code] = match (DisplayNameRules::violation($value)) {
            DisplayNameRules::FORBIDDEN_CHARACTER => [$constraint->forbiddenCharacterMessage, self::FORBIDDEN_CHARACTER_ERROR],
            DisplayNameRules::EMOJI => [$constraint->emojiMessage, self::EMOJI_ERROR],
            DisplayNameRules::STACKED_MARKS => [$constraint->stackedMarksMessage, self::STACKED_MARKS_ERROR],
            DisplayNameRules::NO_LETTER_OR_DIGIT => [$constraint->noLetterOrDigitMessage, self::NO_LETTER_OR_DIGIT_ERROR],
            DisplayNameRules::LEADING_AT => [$constraint->leadingAtMessage, self::LEADING_AT_ERROR],
            DisplayNameRules::RESERVED => [$constraint->reservedMessage, self::RESERVED_ERROR],
            default => $this->userRepository->isUsernameOfAnotherUser($value, $owner)
                ? [$constraint->usernameTakenMessage, self::USERNAME_TAKEN_ERROR]
                : [null, null],
        };

        if ($message !== null) {
            $this->context->buildViolation($message)->setCode($code)->addViolation();
        }
    }

    /**
     * Whose name this is, and what they have saved. A stage name belongs to the member named by the
     * route, not to the admin who may be editing it.
     *
     * @return array{?User, ?string}
     */
    private function ownerAndStoredName(DisplayName $constraint): array
    {
        if ($constraint->owner === DisplayName::OWNER_MEMBERSHIP) {
            $membershipId = (string) $this->requestStack->getCurrentRequest()?->attributes->get('id');
            $membership = Uuid::isValid($membershipId) ? $this->membershipRepository->find($membershipId) : null;

            return $membership instanceof BandSpaceMembership ? [$membership->user, $membership->stageName] : [null, null];
        }

        $editor = $this->security->getUser();

        return $editor instanceof User ? [$editor, $editor->profile->displayName] : [null, null];
    }
}
