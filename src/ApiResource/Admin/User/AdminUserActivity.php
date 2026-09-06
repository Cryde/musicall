<?php declare(strict_types=1);

namespace App\ApiResource\Admin\User;

/**
 * What an account has produced, which is the part of the summary a moderation decision rests on.
 *
 * Its own object rather than five more fields on AdminUser, so the collection can leave it null and
 * mean it: the list answers "which account is this", the item answers "what have they done", and
 * counting five tables per row would make a search page pay for a question it is not asking.
 */
class AdminUserActivity
{
    public int $publications = 0;
    public int $comments = 0;
    public int $forumPosts = 0;
    public int $musicianAnnounces = 0;
    public int $bandSpaces = 0;
}
