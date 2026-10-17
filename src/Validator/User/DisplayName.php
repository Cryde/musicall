<?php

declare(strict_types=1);

namespace App\Validator\User;

use Symfony\Component\Validator\Constraint;

/** A name a user chooses for themselves: their profile name, or their stage name in a band. */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DisplayName extends Constraint
{
    /** The name belongs to whoever edits it: their profile name. */
    public const string OWNER_EDITOR = 'editor';
    /** The name belongs to the member named by the route's membership id: a stage name. */
    public const string OWNER_MEMBERSHIP = 'membership';


    public string $forbiddenCharacterMessage = 'profile.display_name.forbidden_character';
    public string $emojiMessage = 'profile.display_name.emoji';
    public string $stackedMarksMessage = 'profile.display_name.stacked_marks';
    public string $noLetterOrDigitMessage = 'profile.display_name.no_letter_or_digit';
    public string $leadingAtMessage = 'profile.display_name.leading_at';
    public string $reservedMessage = 'profile.display_name.reserved';
    public string $usernameTakenMessage = 'profile.display_name.username_taken';

    public function __construct(
        public string $owner = self::OWNER_EDITOR,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
