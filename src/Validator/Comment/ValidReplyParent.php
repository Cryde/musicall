<?php

declare(strict_types=1);

namespace App\Validator\Comment;

use Symfony\Component\Validator\Constraint;

/**
 * Class-level constraint on a comment-creation payload. Validates that, if `parentId`
 * is set, it references an existing root comment in the same thread.
 *
 * Lives at class level (not on the `parentId` property) because the rule depends on
 * both `parentId` AND `thread` simultaneously.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidReplyParent extends Constraint
{
    public const string NOT_FOUND_CODE = 'music_all_42c5d8a0-4f2c-4d62-9a8d-7a4dba7e9d11';
    public const string WRONG_THREAD_CODE = 'music_all_b7a8c419-2e2d-4f3a-9c19-2f0a8c7f5b40';
    public const string ALREADY_NESTED_CODE = 'music_all_c6fa90d1-1e3f-4b18-a9d8-1ad9ee2d27a5';

    public string $notFoundMessage = 'Commentaire parent introuvable.';
    public string $wrongThreadMessage = "Le commentaire parent n'appartient pas à ce fil de discussion.";
    public string $alreadyNestedMessage = 'Vous ne pouvez pas répondre à une réponse.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
