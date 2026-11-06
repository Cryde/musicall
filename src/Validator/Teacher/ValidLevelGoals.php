<?php

declare(strict_types=1);

namespace App\Validator\Teacher;

use Symfony\Component\Validator\Constraint;

/** The goals of a teacher profile name only levels the same profile accepts, each once (#1312). */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidLevelGoals extends Constraint
{
    public const string ERROR_CODE_NOT_ACCEPTED = 'music_all_6d1f8a52-7c3e-4b9a-9f0d-2e5b8c4a1f73';
    public const string ERROR_CODE_DUPLICATE = 'music_all_b2e47c90-1a5d-4f86-8c3b-9d07e6f2a548';

    public string $notAcceptedMessage = 'profile.teacher.level_goal.level_not_accepted';
    public string $duplicateMessage = 'profile.teacher.level_goal.level_duplicate';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
