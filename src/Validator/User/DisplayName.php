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


    public string $forbiddenCharacterMessage = 'Ce nom contient des caractères non autorisés';
    public string $emojiMessage = 'Ce nom ne peut pas contenir d\'emoji';
    public string $stackedMarksMessage = 'Ce nom contient trop d\'accents à la suite';
    public string $noLetterOrDigitMessage = 'Ce nom doit contenir au moins une lettre ou un chiffre';
    public string $leadingAtMessage = 'Ce nom ne peut pas commencer par @';
    public string $reservedMessage = 'Ce nom est réservé';
    public string $usernameTakenMessage = 'Ce nom est le nom d\'utilisateur d\'un autre membre';

    public function __construct(
        public string $owner = self::OWNER_EDITOR,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
